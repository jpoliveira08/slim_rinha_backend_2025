CREATE TABLE payments (
    correlation_id VARCHAR(36) PRIMARY KEY,  -- Use VARCHAR instead of UUID for less memory
    processor CHAR(1) NOT NULL CHECK (processor IN ('D', 'F')),  -- D=default, F=fallback
    amount DECIMAL(15,2) NOT NULL,
    processed_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_payments_proc_time ON payments(processed_at);

-- Should I only add data here when is processed?