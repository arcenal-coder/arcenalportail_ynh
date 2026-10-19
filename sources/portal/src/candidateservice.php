<?php
// SPDX-License-Identifier: GPL-3.0-or-later
declare(strict_types=1);
namespace Vigilance;

final class CandidateService
{
    public const FIELDS = ['first_name'=>'Prénom','last_name'=>'Nom','email'=>'E-mail personnel','phone'=>'Téléphone','birth'=>'Date de naissance','birth_place'=>'Lieu de naissance','address'=>'Adresse postale','postal'=>'Code postal','city'=>'Ville','nationality'=>'Nationalité'];
    public const TERMS = ['job'=>'Poste proposé','site'=>'Lieu d’affectation','start'=>'Date prévisionnelle','contract'=>'Type de contrat','status'=>'Statut','salary'=>'Salaire brut','hours'=>'Base de travail','meal'=>'Repas','travel'=>'Déplacements','equipment'=>'Équipements','representative'=>'Représentant de l’entreprise','additional'=>'Autres conditions du FORM S 13'];

    public function __construct(private readonly JourneyContext $context) {}

    public function listing(): array
    {
        $this->context->requireAny(['admin','direction']);
        return $this->context->db->rows('SELECT p.id,p.first_name,p.last_name,p.email,c.stage,c.revision FROM candidature c JOIN person p ON p.id=c.person_id WHERE c.company_id=? ORDER BY p.last_name,p.first_name',[$this->context->access->company]);
    }

    public function get(string $id): array
    {
        $access = $this->context->access;
        $access->account();
        $rows = $this->context->db->rows('SELECT p.*,c.stage,c.details,c.revision AS candidate_revision FROM person p JOIN candidature c ON c.person_id=p.id WHERE c.company_id=? AND p.id=?',[$access->company,$id]);
        $row = $rows[0] ?? null;
        $staff = $access->has('admin') || $access->has('direction');
        if (!$row || (!$staff && (!$access->has('candidate') || $row['account_uid'] !== $access->uid))) throw new JourneyDenied('Candidature non accessible.');
        return $row;
    }

    public function mine(): ?array
    {
        $this->context->requireAny(['candidate']);
        $rows = $this->context->db->rows('SELECT person_id FROM candidature c JOIN person p ON p.id=c.person_id WHERE c.company_id=? AND p.account_uid=?',[$this->context->access->company,$this->context->access->uid]);
        return $rows ? $this->get($rows[0]['person_id']) : null;
    }

    public function create(string $uid, string $email): string
    {
        return $this->context->db->transaction(function () use ($uid,$email): string {
            $this->context->requireAny(['admin']);
            Identity::validate($uid);
            if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>180) throw new JourneyInput('E-mail personnel valide requis.');
            $this->authorizeCandidate($uid);
            $id = bin2hex(random_bytes(16));
            $company = $this->context->access->company;
            $this->context->db->execute('INSERT INTO person(id,company_id,first_name,last_name,email,account_uid,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?)',[$id,$company,'','',$email,$uid,'candidate',gmdate('c'),gmdate('c')]);
            $this->context->db->execute('INSERT INTO candidature(person_id,company_id) VALUES (?,?)',[$id,$company]);
            $this->context->notify('Votre dossier de candidature','Votre dossier vous attend dans votre profil Candidat.',[],$uid);
            $this->context->audit('candidate.create',$id,1);
            return $id;
        });
    }

    private function authorizeCandidate(string $uid): void
    {
        $company = $this->context->access->company;
        $account = $this->context->db->rows('SELECT * FROM account WHERE company_id=? AND uid=?',[$company,$uid])[0] ?? null;
        if (!$account || $this->context->db->rows('SELECT id FROM person WHERE company_id=? AND account_uid=?',[$company,$uid])) throw new JourneyInput('Sélectionnez un compte SSO observé, sans dossier associé.');
        $roles = json_decode($account['roles'],true,16,JSON_THROW_ON_ERROR);
        if (in_array('employee',$roles,true)) throw new JourneyInput('Ce compte possède déjà le profil Équipier.');
        $roles = array_values(array_unique(array_merge($roles,['candidate'])));
        $this->context->db->execute('UPDATE account SET enabled=1,roles=?,revision=revision+1 WHERE company_id=? AND uid=?',[json_encode($roles,JSON_THROW_ON_ERROR),$company,$uid]);
    }

    public function save(string $id, int $revision, array $input, bool $submit): void
    {
        $this->context->db->transaction(function () use ($id,$revision,$input,$submit): void {
            $this->context->requireAny(['candidate']);
            $before = $this->get($id);
            if ($before['account_uid'] !== $this->context->access->uid || $before['stage'] !== 'draft') throw new JourneyDenied('Ce dossier ne peut plus être modifié.');
            $values = $this->validateDetails($input,$submit);
            $this->changeStage($id,$revision,$submit?'submitted':'draft',json_encode($values,JSON_THROW_ON_ERROR));
            $this->context->db->execute('UPDATE person SET first_name=?,last_name=?,email=?,revision=revision+1,updated_at=? WHERE id=? AND company_id=?',[$values['first_name'],$values['last_name'],$values['email'],gmdate('c'),$id,$this->context->access->company]);
            $this->context->audit($submit?'candidate.submit':'candidate.save',$id,$revision+1);
            if ($submit) $this->context->notify('Dossier candidat soumis','Un dossier de candidature est prêt à être examiné.',['admin']);
        });
    }

    private function validateDetails(array $input, bool $submit): array
    {
        if (array_diff(array_keys($input),array_keys(self::FIELDS))) throw new JourneyInput('Champ candidat non autorisé.');
        $values = [];
        foreach (self::FIELDS as $key => $label) $values[$key] = JourneyContext::text($input,$key,180,$submit && in_array($key,['first_name','last_name','email','phone','address','postal','city'],true));
        if ($values['email'] !== '' && !filter_var($values['email'],FILTER_VALIDATE_EMAIL)) throw new JourneyInput('E-mail personnel invalide.');
        $date = $values['birth'];
        if ($date !== '' && (!JourneyContext::validDate($date) || $date>gmdate('Y-m-d'))) throw new JourneyInput('Date de naissance invalide.');
        return $values;
    }

    private function changeStage(string $id, int $revision, string $stage, ?string $details = null): void
    {
        $count = $this->context->db->execute('UPDATE candidature SET stage=?,details=COALESCE(?,details),revision=revision+1,submitted_at=CASE WHEN ?=\'submitted\' THEN ? ELSE submitted_at END WHERE company_id=? AND person_id=? AND revision=?',[$stage,$details,$stage,gmdate('c'),$this->context->access->company,$id,$revision]);
        if ($count !== 1) throw new JourneyDenied('Le dossier a changé. Rechargez la page.');
    }

    public function offers(string $id): array
    {
        $this->get($id);
        return $this->context->db->rows('SELECT * FROM interest_offer WHERE company_id=? AND person_id=? ORDER BY revision DESC',[$this->context->access->company,$id]);
    }

    public function publishOffer(string $id, int $revision, array $terms): void
    {
        $this->context->db->transaction(function () use ($id,$revision,$terms): void {
            $this->context->requireAny(['admin','direction']);
            $candidate = $this->get($id);
            if (!in_array($candidate['stage'],['submitted','refused','offer'],true)) throw new JourneyDenied('Une candidature soumise est requise.');
            $values = $this->validateTerms($terms);
            $this->changeStage($id,$revision,'offer');
            $history = $this->offers($id);
            $next = ($history ? (int)$history[0]['revision'] : 0)+1;
            $this->context->db->execute("UPDATE interest_offer SET state='superseded' WHERE person_id=? AND state='pending'",[$id]);
            $this->context->db->execute('INSERT INTO interest_offer(person_id,company_id,revision,terms,created_at) VALUES (?,?,?,?,?)',[$id,$this->context->access->company,$next,json_encode($values,JSON_THROW_ON_ERROR),gmdate('c')]);
            $this->context->notify('Validation d’intérêt disponible','Consultez la nouvelle version du FORM S 13 et transmettez votre décision.',[],$candidate['account_uid']);
            $this->context->audit('offer.publish',$id,$next);
        });
    }

    private function validateTerms(array $input): array
    {
        if (array_diff(array_keys($input),array_keys(self::TERMS))) throw new JourneyInput('Condition non autorisée.');
        $values = [];
        foreach (self::TERMS as $key => $label) $values[$key] = JourneyContext::text($input,$key,2000,in_array($key,['job','site','start','contract','salary','hours','representative'],true));
        $date = $values['start'];
        if (!JourneyContext::validDate($date)) throw new JourneyInput('Date de début invalide.');
        return $values;
    }

    public function decide(string $id, int $offerId, bool $accept, string $reason): void
    {
        $this->context->db->transaction(function () use ($id,$offerId,$accept,$reason): void {
            $this->context->requireAny(['candidate']);
            $candidate = $this->get($id);
            $offer = $this->offers($id)[0] ?? null;
            if ($candidate['account_uid'] !== $this->context->access->uid || $candidate['stage'] !== 'offer' || !$offer || (int)$offer['id'] !== $offerId || $offer['state'] !== 'pending') throw new JourneyDenied('Cette proposition ne peut plus être validée.');
            $reason = JourneyContext::text(['reason'=>$reason],'reason',2000,!$accept);
            $this->context->db->execute('UPDATE interest_offer SET state=?,reason=?,decided_at=? WHERE id=?',[$accept?'accepted':'refused',$reason,gmdate('c'),$offerId]);
            $this->changeStage($id,(int)$candidate['candidate_revision'],$accept?'accepted':'refused');
            if ($accept) $this->activateEmployee($candidate,$offer);
            $this->context->notify($accept?'Validation d’intérêt acceptée':'FORM S 13 refusé ou à modifier',$accept?'Un candidat a accepté sa proposition. Son accueil peut commencer.':'Une proposition requiert une reprise par la Direction. Consultez le dossier candidat.',$accept?['admin','rh']:['direction']);
            $this->context->audit($accept?'offer.accept':'offer.refuse',$id,(int)$offer['revision']);
        });
    }

    private function activateEmployee(array $candidate, array $offer): void
    {
        $access = $this->context->access;
        $account = $access->account();
        $roles = array_values(array_unique(array_merge(array_diff($account['roles'],['candidate']),['employee'])));
        $terms = json_decode($offer['terms'],true,16,JSON_THROW_ON_ERROR);
        $this->context->db->execute('UPDATE account SET roles=?,revision=revision+1 WHERE company_id=? AND uid=?',[json_encode($roles,JSON_THROW_ON_ERROR),$access->company,$access->uid]);
        $this->context->db->execute("UPDATE person SET status='onboarding',job=?,starts_on=?,revision=revision+1 WHERE id=?",[$terms['job'],$terms['start'],$candidate['id']]);
    }
}
