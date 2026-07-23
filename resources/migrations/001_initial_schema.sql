CREATE TABLE IF NOT EXISTS cases (
    id VARCHAR(36) PRIMARY KEY,
    business_context TEXT NOT NULL,
    status VARCHAR(50) NOT NULL,
    created_at VARCHAR(32) NOT NULL,
    updated_at VARCHAR(32) NULL
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id VARCHAR(36) PRIMARY KEY,
    aggregate_id VARCHAR(36) NOT NULL,
    action VARCHAR(100) NOT NULL,
    payload TEXT NOT NULL,
    occurred_at VARCHAR(32) NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_audit_logs_aggregate ON audit_logs(aggregate_id);
