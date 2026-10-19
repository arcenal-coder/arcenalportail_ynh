"""Runner-only real local-mail check after native upgrade. Never deployed to the app."""
import hashlib
import json
import os
import re
import subprocess
import sys
import time
from email import policy
from email.parser import Parser
from email.utils import getaddresses
from contextlib import contextmanager

STAGE = 'start'


def run(*args, timeout=45):
    global STAGE
    STAGE = 'command_' + args[0]
    result = subprocess.run(args, capture_output=True, text=True, timeout=timeout)
    if result.returncode:
        raise RuntimeError("Native mail command failed")
    return result.stdout.strip()


def safe_local_message(content, sender, recipient, url):
    raw = re.split(r'(?:^|\n)text: ?', content, maxsplit=1)
    if len(raw) != 2:
        return False
    message = Parser(policy=policy.default).parsestr(raw[1].lstrip('\r\n'))
    body = message.get_content()
    return (getaddresses(message.get_all('From', [])) == [('', sender)]
            and getaddresses(message.get_all('To', [])) == [('', recipient)]
            and isinstance(body, str) and url in body
            and 'NATIVE_INTERNAL_ONLY' not in body and 'NATIVE_PRIVATE_BODY' not in body)


@contextmanager
def manual_worker_only():
    global STAGE
    # Test container only: avoid racing a minute-boundary cron invocation.
    active = subprocess.run(['systemctl', 'is-active', '--quiet', 'cron'], capture_output=True).returncode == 0
    if active:
        run('systemctl', 'stop', 'cron')
    failed_stage = None
    try:
        yield
    except Exception:
        failed_stage = STAGE
        raise
    finally:
        if active:
            run('systemctl', 'start', 'cron')
        if failed_stage is not None:
            STAGE = failed_stage


@contextmanager
def blocked_smtp_egress():
    global STAGE
    STAGE = 'smtp_egress_policy'
    table = 'vigilance_mail_' + str(os.getpid())
    rules = f'''table inet {table} {{
  chain output {{
    type filter hook output priority 0; policy accept;
    meta nfproto ipv4 tcp dport {{ 25, 465, 587 }} ip daddr != 127.0.0.0/8 reject
    meta nfproto ipv6 tcp dport {{ 25, 465, 587 }} ip6 daddr != ::1 reject
  }}
}}
'''
    # YunoHost 12 supplies nftables, not the optional iptables compatibility tools.
    result = subprocess.run(['nft','-f','-'],input=rules,capture_output=True,text=True,timeout=15)
    if result.returncode:
        raise RuntimeError('Local SMTP egress blocking failed')
    try:
        yield
    finally:
        subprocess.run(['nft','delete','table','inet',table],capture_output=True,timeout=15,check=True)


def check(app):
    global STAGE
    if not re.fullmatch(r"arcenalportail(?:__[0-9]+)?", app):
        raise ValueError("Unexpected app")
    install = run("yunohost", "app", "setting", app, "install_dir")
    domain = run("yunohost", "app", "setting", app, "domain")
    if install != "/var/www/" + app:
        raise ValueError("Unexpected installation path")
    # Existing package-check account only; its primary mailbox is provisioned locally.
    user = json.loads(run("yunohost", "user", "info", "package_checker", "--output-as", "json"))
    STAGE = 'local_recipient'
    recipient = user["mail"]
    domains = json.loads(run("yunohost", "domain", "list", "--output-as", "json"))["domains"]
    if not isinstance(recipient, str) or recipient.rsplit("@", 1)[-1] not in domains:
        raise ValueError("Recipient must be a local YunoHost mailbox")
    from pathlib import Path
    cron = Path("/etc/cron.d/" + app + "-journey-mail")
    STAGE = 'cron_file'
    cron_text = cron.read_text()
    if "__" in cron_text.replace(app, "") or f" {app} /usr/bin/env " not in cron_text:
        raise ValueError("Cron not rendered with application identity")
    if cron.stat().st_uid != 0 or cron.stat().st_mode & 0o777 != 0o644:
        raise ValueError("Unsafe cron ownership/mode")
    executable = re.search(r"/usr/bin/php([0-9.]+) ", cron_text)
    if not executable or not os.access("/usr/bin/php" + executable[1], os.X_OK):
        raise ValueError("PHP CLI missing")
    php = "/usr/bin/php" + executable[1]
    if f'ARCENAL_PORTAL_CONFIG="{install}/config.php"' not in cron_text or f'"{install}/bin/send-journey-mail.php"' not in cron_text:
        raise ValueError("Wrong cron target")
    php_code = r'''
require getenv('VIGILANCE_TEST_INSTALL').'/src/load.php';
$c=\Vigilance\Config::load(getenv('ARCENAL_PORTAL_CONFIG'));
$db=new \Vigilance\Database($c['database_path']); $db->checkSchema();
if ($db->rows("SELECT m.notification_id FROM journey_mail m JOIN journey_notification n ON n.id=m.notification_id WHERE n.company_id=? AND m.state='pending'",[(int)$c['company_id']])) throw new RuntimeException('Nonempty native fixture queue');
$a=new \Vigilance\Access($db,(int)$c['company_id'],'package_checker','admin');
$account=$a->account();
(new \Vigilance\AdminService($db,$a))->updateAccount('package_checker',(int)$account['revision'],$account['roles'],$account['teams'],true,(bool)$account['directory'],getenv('VIGILANCE_TEST_MAIL_TO'));
$context=new \Vigilance\JourneyContext($db,$a);
$db->transaction(fn()=>$context->notify('NATIVE_INTERNAL_ONLY','NATIVE_PRIVATE_BODY',[],'package_checker','candidate.opened'));
$id=(int)$db->rows('SELECT MAX(id) AS id FROM journey_notification')[0]['id'];
echo json_encode(['id'=>$id,'company'=>(int)$c['company_id'],'url'=>$c['public_url'],'from'=>$c['mail_from']]);
'''
    prefix = ["runuser", "-u", app, "--", "env", "ARCENAL_PORTAL_CONFIG=" + install + "/config.php"]
    identity = json.loads(run(*prefix, "VIGILANCE_TEST_INSTALL=" + install, "VIGILANCE_TEST_MAIL_TO=" + recipient, php, "-r", php_code))
    if identity["from"] != app + "@" + domain:
        raise ValueError("Sender mismatches system-user resource default")
    STAGE = 'sender_map'
    sender_map = Path("/etc/postfix/app_senders_login_maps").read_text()
    if not any(line.split() == [identity["from"], app] for line in sender_map.splitlines()):
        raise ValueError("Sender is not provisioned for the application")
    # No external SMTP egress during the check, even on a misconfigured fixture.
    with blocked_smtp_egress():
        run("systemctl", "start", "postfix", "dovecot")
        accepted = json.loads(run(*prefix, php, install + "/bin/send-journey-mail.php"))
        if accepted["sent"] != 1 or accepted["retry"] or accepted["failed"]:
            raise ValueError("Real local MTA did not accept the message")
        message_id = hashlib.sha256(f'{identity["url"]}|{identity["company"]}|{identity["id"]}'.encode()).hexdigest() + "@" + domain
        delivered = ""
        for _ in range(30):
            delivered = run("doveadm", "search", "-u", "package_checker", "header", "Message-ID", "<" + message_id + ">")
            if delivered:
                break
            time.sleep(1)
        if not delivered:
            raise ValueError("Local mailbox did not receive message")
        content = run("doveadm", "fetch", "-u", "package_checker", "hdr.from hdr.to text", "header", "Message-ID", "<" + message_id + ">")
        if not safe_local_message(content, identity['from'], recipient, identity['url']):
            raise ValueError("Unexpected message headers or private content")
        # The second actual invocation must not resubmit a sent event.
        repeated = json.loads(run(*prefix, php, install + "/bin/send-journey-mail.php"))
        if repeated["sent"]:
            raise ValueError("Sent event was submitted twice")
        return {"schema": "vigilance-native-mail/v1", "cron": True, "sender": True,
                "mta_accepted": True, "delivered_local": True, "private_content": True, "no_resend": True}


if __name__ == "__main__":
    try:
        with manual_worker_only():
            print(json.dumps(check(sys.argv[1])))
    except (ValueError, RuntimeError) as error:
        raise SystemExit('VIGIlance native local-mail check failed: ' + str(error)) from None
    except Exception as error:
        # Command output can contain addresses or server details: never print it.
        raise SystemExit('VIGIlance native local-mail check failed: stage=' + STAGE + ' exception=' + type(error).__name__) from None
