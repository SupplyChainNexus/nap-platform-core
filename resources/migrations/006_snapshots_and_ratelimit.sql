CREATE TABLE IF NOT EXISTS case_snapshots (
    aggregate_id TEXT PRIMARY KEY,
    version INTEGER NOT NULL,
    state TEXT NOT NULL,
    created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS rate_limits (
    key TEXT PRIMARY KEY,
    tokens REAL NOT NULL,
    last_updated INTEGER NOT NULL
);
