ALTER TABLE account ADD COLUMN notification_email TEXT NOT NULL DEFAULT '';
CREATE TABLE journey_mail (
 notification_id INTEGER PRIMARY KEY REFERENCES journey_notification(id),
 event TEXT NOT NULL CHECK(event IN ('candidate.opened','candidate.submitted','offer.published','offer.refused','offer.accepted','welcome.completed')),
 required_roles TEXT NOT NULL DEFAULT '[]',
 state TEXT NOT NULL DEFAULT 'pending' CHECK(state IN ('pending','sent','cancelled','failed')),
 attempts INTEGER NOT NULL DEFAULT 0,
 available_at INTEGER NOT NULL DEFAULT 0,
 last_error TEXT NOT NULL DEFAULT '',
 sent_at TEXT
);
CREATE INDEX journey_mail_due ON journey_mail(state,available_at);
