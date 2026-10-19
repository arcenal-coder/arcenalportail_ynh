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


def run(*args, timeout=45):
    result = subprocess.run(args, capture_output=True, text=True, timeout=timeout)
    if result.returncode:
        raise RuntimeError("Native mail command failed")
    return result.stdout.strip()


def check(app):
    if not re.fullmatch(r"arcenalportail(?:__[0-9]+)?", app):
        raise ValueError("Unexpected app")
    install = run("yunohost", "app", "setting", app, "install_dir")
    domain = run("yunohost", "app", "setting", app, "domain")
    if install != "/var/www/" + app:
        raise ValueError("Unexpected installation path")
    # Existing package-check account only; its primary mailbox is provisioned locally.
    user = json.loads(run("yunohost", "user", "info", "package_checker", "--output-as", "json"))
    recipient = user["mail"]
    domains = json.loads(run("yunohost", "domain", "list", "--output-as", "json"))["domains"]
    if not isinstance(recipient, str) or recipient.rsplit("@", 1)[-1] not in domains:
        raise ValueError("Recipient must be a local YunoHost mailbox")
    from pathlib import Path
    cron = Path("/etc/cron.d/" + app + "-journey-mail")
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
    sender_map = Path("/etc/postfix/app_senders_login_maps").read_text()
    if not any(line.split() == [identity["from"], app] for line in sender_map.splitlines()):
        raise ValueError("Sender is not provisioned for the application")
    # No external SMTP egress during the check, even on a misconfigured fixture.
    rules = []
    try:
        for binary, loopback in [("iptables", "127.0.0.0/8"), ("ip6tables", "::1/128")]:
            rule = ["OUTPUT", "-p", "tcp", "-m", "multiport", "--dports", "25,465,587", "!", "-d", loopback, "-j", "REJECT"]
            run(binary, "-I", *rule)
            rules.append((binary, rule))
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
        raw = re.split(r'(?:^|\n)text: ?', content, maxsplit=1)
        if len(raw) != 2:
            raise ValueError('Local message not readable')
        message = Parser(policy=policy.default).parsestr(raw[1])
        body = message.get_content()
        if (getaddresses(message.get_all('From',[])) != [('', identity['from'])]
                or getaddresses(message.get_all('To',[])) != [('', recipient)]
                or identity['url'] not in body or 'NATIVE_INTERNAL_ONLY' in body or 'NATIVE_PRIVATE_BODY' in body):
            raise ValueError("Unexpected message headers or private content")
        # The second actual invocation must not resubmit a sent event.
        repeated = json.loads(run(*prefix, php, install + "/bin/send-journey-mail.php"))
        if repeated["sent"]:
            raise ValueError("Sent event was submitted twice")
        return {"schema": "vigilance-native-mail/v1", "cron": True, "sender": True,
                "mta_accepted": True, "delivered_local": True, "private_content": True, "no_resend": True}
    finally:
        for binary, rule in reversed(rules):
            subprocess.run([binary, "-D", *rule], capture_output=True, timeout=15)


if __name__ == "__main__":
    try:
        print(json.dumps(check(sys.argv[1])))
    except (ValueError, RuntimeError) as error:
        raise SystemExit('VIGIlance native local-mail check failed: ' + str(error)) from None
    except Exception:
        # Command output can contain addresses or server details: never print it.
        raise SystemExit("VIGIlance native local-mail check failed") from None
