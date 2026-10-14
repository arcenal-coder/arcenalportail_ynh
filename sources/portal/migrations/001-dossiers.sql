CREATE TABLE company (
 id INTEGER PRIMARY KEY, name TEXT NOT NULL, logo TEXT NOT NULL DEFAULT '', revision INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE account (
 company_id INTEGER NOT NULL REFERENCES company(id), uid TEXT NOT NULL,
 enabled INTEGER NOT NULL DEFAULT 0 CHECK(enabled IN (0,1)),
 roles TEXT NOT NULL DEFAULT '[]', teams TEXT NOT NULL DEFAULT '[]',
 directory INTEGER NOT NULL DEFAULT 0 CHECK(directory IN (0,1)),
 revision INTEGER NOT NULL DEFAULT 1,
 PRIMARY KEY(company_id,uid)
);
CREATE TABLE person (
 id TEXT PRIMARY KEY, company_id INTEGER NOT NULL REFERENCES company(id),
 first_name TEXT NOT NULL, last_name TEXT NOT NULL, job TEXT NOT NULL DEFAULT '',
 team TEXT NOT NULL DEFAULT '', manager_uid TEXT NOT NULL DEFAULT '',
 email TEXT NOT NULL DEFAULT '', account_uid TEXT,
 status TEXT NOT NULL CHECK(status IN ('candidate','onboarding','active','leaving','archived')),
 starts_on TEXT NOT NULL DEFAULT '', ends_on TEXT NOT NULL DEFAULT '',
 revision INTEGER NOT NULL DEFAULT 1,
 created_at TEXT NOT NULL, updated_at TEXT NOT NULL,
 UNIQUE(company_id,account_uid),
 FOREIGN KEY(company_id,account_uid) REFERENCES account(company_id,uid)
);
CREATE INDEX person_company ON person(company_id,last_name,first_name);
CREATE INDEX person_team ON person(company_id,team);
CREATE TABLE audit (
 id INTEGER PRIMARY KEY AUTOINCREMENT, company_id INTEGER NOT NULL REFERENCES company(id),
 actor TEXT NOT NULL, action TEXT NOT NULL, target TEXT NOT NULL,
 revision INTEGER NOT NULL, created_at TEXT NOT NULL
);
