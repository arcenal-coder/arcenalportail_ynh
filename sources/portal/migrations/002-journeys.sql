CREATE TABLE candidature (
 person_id TEXT PRIMARY KEY REFERENCES person(id), company_id INTEGER NOT NULL REFERENCES company(id),
 stage TEXT NOT NULL DEFAULT 'draft' CHECK(stage IN ('draft','submitted','offer','refused','accepted')),
 details TEXT NOT NULL DEFAULT '{}', revision INTEGER NOT NULL DEFAULT 1, submitted_at TEXT
);
CREATE TABLE interest_offer (
 id INTEGER PRIMARY KEY AUTOINCREMENT, person_id TEXT NOT NULL REFERENCES person(id),
 company_id INTEGER NOT NULL REFERENCES company(id), revision INTEGER NOT NULL,
 terms TEXT NOT NULL, state TEXT NOT NULL DEFAULT 'pending' CHECK(state IN ('pending','accepted','refused','superseded')),
 reason TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL, decided_at TEXT, UNIQUE(person_id,revision)
);
CREATE TABLE welcome_content (
 id INTEGER PRIMARY KEY AUTOINCREMENT, company_id INTEGER NOT NULL REFERENCES company(id),
 code TEXT NOT NULL, revision INTEGER NOT NULL, title TEXT NOT NULL, payload TEXT NOT NULL,
 asset TEXT NOT NULL DEFAULT '', mime TEXT NOT NULL DEFAULT '', repeat_required INTEGER NOT NULL CHECK(repeat_required IN (0,1)),
 published_at TEXT NOT NULL, UNIQUE(company_id,code,revision)
);
CREATE TABLE welcome_progress (
 company_id INTEGER NOT NULL REFERENCES company(id), uid TEXT NOT NULL, step INTEGER NOT NULL DEFAULT 0,
 completed_at TEXT, attestation TEXT NOT NULL DEFAULT '{}', PRIMARY KEY(company_id,uid),
 FOREIGN KEY(company_id,uid) REFERENCES account(company_id,uid)
);
CREATE TABLE welcome_proof (
 id INTEGER PRIMARY KEY AUTOINCREMENT, company_id INTEGER NOT NULL REFERENCES company(id), uid TEXT NOT NULL,
 content_id INTEGER NOT NULL REFERENCES welcome_content(id), score INTEGER, created_at TEXT NOT NULL,
 FOREIGN KEY(company_id,uid) REFERENCES account(company_id,uid)
);
CREATE TABLE welcome_video (
 company_id INTEGER NOT NULL REFERENCES company(id), uid TEXT NOT NULL, content_id INTEGER NOT NULL REFERENCES welcome_content(id),
 seconds REAL NOT NULL DEFAULT 0, tick_at REAL NOT NULL, PRIMARY KEY(company_id,uid,content_id),
 FOREIGN KEY(company_id,uid) REFERENCES account(company_id,uid)
);
CREATE TABLE welcome_obligation (
 company_id INTEGER NOT NULL REFERENCES company(id), uid TEXT NOT NULL, content_id INTEGER NOT NULL REFERENCES welcome_content(id),
 fulfilled_at TEXT, proof_after_id INTEGER NOT NULL DEFAULT 0, PRIMARY KEY(company_id,uid,content_id),
 FOREIGN KEY(company_id,uid) REFERENCES account(company_id,uid)
);
CREATE TABLE journey_notification (
 id INTEGER PRIMARY KEY AUTOINCREMENT, company_id INTEGER NOT NULL REFERENCES company(id), uid TEXT NOT NULL,
 title TEXT NOT NULL, body TEXT NOT NULL, created_at TEXT NOT NULL,
 FOREIGN KEY(company_id,uid) REFERENCES account(company_id,uid)
);
CREATE INDEX journey_notifications_recipient ON journey_notification(company_id,uid,id);
