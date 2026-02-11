-- MySQL 8.0+

CREATE TABLE IF NOT EXISTS shipments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tracking_number VARCHAR(64) NOT NULL,
  carrier VARCHAR(64) NULL,
  label VARCHAR(255) NULL,
  status ENUM('created','in_transit','out_for_delivery','delivered','exception','unknown') NOT NULL DEFAULT 'created',
  last_event_at DATETIME NULL,
  archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_shipments_tracking_number (tracking_number),
  KEY idx_shipments_status (status),
  KEY idx_shipments_last_event_at (last_event_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tracking_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  shipment_id BIGINT UNSIGNED NOT NULL,
  event_time DATETIME NOT NULL,
  location VARCHAR(255) NULL,
  description TEXT NOT NULL,
  raw_payload JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tracking_events_shipment_time (shipment_id, event_time),
  CONSTRAINT fk_tracking_events_shipment_id
    FOREIGN KEY (shipment_id) REFERENCES shipments(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
