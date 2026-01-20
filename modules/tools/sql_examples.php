<?php
// modules/tools/sql_examples.php
// Central place that stores SQL example strings so they are available inside PHP modules.
// Purpose: 1) Educational SQL examples grouped by topic (sql/queries equivalent in PHP)
//          2) Feature-specific runtime SQL snippets used by handlers (e.g., ambulance/icu updates)

function get_sql_examples() {
    static $examples = null;
    if ($examples !== null) return $examples;

    $examples = [];

    // 01_select_variants.sql
    $examples['01_select_variants'] = <<<'SQL'
SELECT id, name, email FROM users LIMIT 50;

-- DISTINCT: zones that have at least one safe person
SELECT DISTINCT zone_id FROM `safe` WHERE zone_id IS NOT NULL;

-- ALL (explicit) - MySQL treats ALL as default
SELECT ALL zone_id FROM `safe` WHERE outbreak_status = 'infected' LIMIT 50;

-- Column alias and expression
SELECT name, morale, stamina, (morale + stamina) AS total_status FROM `safe` LIMIT 50;

-- Pattern matching: find campaigns and vaccines by name
SELECT * FROM medical_campaign WHERE title LIKE '%vaccine%' OR description LIKE '%vaccine%';
SELECT * FROM medical_equipments WHERE name LIKE '%covid%' OR description LIKE '%covid%';
SQL;

    // 02_range_and_set_membership.sql
    $examples['02_range_and_set_membership'] = <<<'SQL'
-- Range searches and set membership on outbreaks and dates
SELECT * FROM infection_events WHERE created_at BETWEEN NOW() - INTERVAL 7 DAY AND NOW() AND cases > 5;

SELECT * FROM zones WHERE population BETWEEN 1000 AND 100000;

SELECT * FROM `safe` WHERE zone_id IN (1,2,3);
SELECT * FROM `safe` WHERE zone_id NOT IN (1,2,3);
SQL;

    // 03_order_by.sql
    $examples['03_order_by'] = <<<'SQL'
SELECT * FROM zones ORDER BY death_count ASC;
SELECT * FROM zones ORDER BY death_count DESC;

SELECT * FROM medical_campaign ORDER BY FIELD(state,'in_progress','todo','done'), capacity DESC;

SELECT * FROM infection_events ORDER BY created_at DESC LIMIT 50;
SQL;

    // 04_aggregates_group_by_having.sql
    $examples['04_aggregates'] = <<<'SQL'
SELECT COUNT(*) AS events_count, COALESCE(SUM(cases),0) AS total_cases FROM infection_events;

SELECT zone_id, SUM(cases) AS total_cases FROM infection_events GROUP BY zone_id ORDER BY total_cases DESC LIMIT 20;

SELECT zone_id, SUM(cases) AS cases_24h FROM infection_events WHERE created_at >= NOW() - INTERVAL 1 DAY GROUP BY zone_id HAVING SUM(cases) > 10;

SELECT campaign_id, COUNT(*) AS registrations FROM medical_campaign_registrations GROUP BY campaign_id HAVING COUNT(*) > 0;
SQL;

    // 05_subqueries.sql
    $examples['05_subqueries'] = <<<'SQL'
SELECT id, name, (SELECT COALESCE(SUM(cases),0) FROM infection_events ie WHERE ie.zone_id = s.zone_id) AS zone_cases
FROM `safe` s LIMIT 50;

SELECT * FROM `safe` WHERE zone_id IN (
  SELECT zone_id FROM infection_events WHERE created_at >= NOW() - INTERVAL 1 DAY GROUP BY zone_id HAVING SUM(cases) > 10
);

SELECT c.id, c.title, cr.reg_count
FROM medical_campaign c
JOIN (
    SELECT campaign_id, COUNT(*) AS reg_count FROM medical_campaign_registrations GROUP BY campaign_id
) cr ON cr.campaign_id = c.id;
SQL;


    // 07_views_and_updatable.sql
    $examples['07_views'] = <<<'SQL'
CREATE OR REPLACE VIEW vw_zone_summary AS
SELECT z.id AS zone_id, z.name AS zone_name, z.population, z.death_count,
    COALESCE(SUM(ie.cases),0) AS total_cases,
    COALESCE(SUM(CASE WHEN ie.created_at >= NOW() - INTERVAL 1 DAY THEN ie.cases ELSE 0 END),0) AS cases_24h
FROM zones z LEFT JOIN infection_events ie ON ie.zone_id = z.id
GROUP BY z.id;

CREATE OR REPLACE VIEW vw_active_campaigns_demo AS
SELECT id, title, zone_id, state, capacity FROM medical_campaign WHERE state <> 'done';
SQL;

    // 08_joins.sql
    $examples['08_joins'] = <<<'SQL'
SELECT s.name AS person_name, s.outbreak_status, z.name AS zone_name
FROM `safe` s JOIN zones z ON s.zone_id = z.id;

SELECT z.id, z.name, ie.cases, ie.created_at
FROM zones z LEFT JOIN infection_events ie ON ie.zone_id = z.id AND ie.created_at >= NOW() - INTERVAL 7 DAY
ORDER BY z.name;

SELECT c.id, c.title, COALESCE(cr.reg_count,0) AS registrations
FROM medical_campaign c LEFT JOIN (
    SELECT campaign_id, COUNT(*) AS reg_count FROM medical_campaign_registrations GROUP BY campaign_id
) cr ON cr.campaign_id = c.id;

SELECT a.id AS person_a, a.name AS name_a, b.id AS person_b, b.name AS name_b
FROM `safe` a JOIN `safe` b ON a.zone_id = b.zone_id AND a.id < b.id;
SQL;

    // 09_window_functions.sql
    $examples['09_window'] = <<<'SQL'
SELECT id, zone_id, cases, created_at,
    SUM(cases) OVER (PARTITION BY zone_id ORDER BY created_at ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS cumulative_cases
FROM infection_events
ORDER BY zone_id, created_at
LIMIT 200;

SELECT id, zone_id, cases, created_at,
    ROW_NUMBER() OVER (PARTITION BY zone_id ORDER BY created_at DESC) AS rn
FROM infection_events
LIMIT 200;
SQL;

    // 10_constraints_and_alter_examples.sql
    $examples['10_constraints'] = <<<'SQL'
ALTER TABLE medical_equipments ADD UNIQUE (name);

-- Example sequence for changing PKs (dangerous)
-- ALTER TABLE infection_events DROP FOREIGN KEY fk_ie_zone;
-- ALTER TABLE zones DROP PRIMARY KEY;
-- ALTER TABLE zones ADD PRIMARY KEY (name);
-- ALTER TABLE infection_events ADD CONSTRAINT fk_ie_zone FOREIGN KEY (zone_id) REFERENCES zones(name);
SQL;

    // Feature-specific SQL snippets used by handlers (for clarity inside PHP)
    $examples['ambulance_insert'] = "INSERT INTO ambulance_requests (user_id, zone_id, details) VALUES (?, ?, ?)";
    $examples['ambulance_update_assign'] = "UPDATE ambulance_requests SET status='assigned', updated_at=NOW() WHERE id = ?";
    $examples['ambulance_update_complete'] = "UPDATE ambulance_requests SET status='completed', updated_at=NOW() WHERE id = ?";
    $examples['ambulance_update_reject'] = "UPDATE ambulance_requests SET status='rejected', updated_at=NOW() WHERE id = ?";

    $examples['icu_insert'] = "INSERT INTO icu_requests (user_id, zone_id, details) VALUES (?, ?, ?)";
    $examples['icu_update_confirm'] = "UPDATE icu_requests SET status='confirmed', updated_at=NOW() WHERE id = ?";
    $examples['icu_update_admit'] = "UPDATE icu_requests SET status='admitted', updated_at=NOW() WHERE id = ?";
    $examples['icu_update_reject'] = "UPDATE icu_requests SET status='rejected', updated_at=NOW() WHERE id = ?";

    return $examples;
}

function run_sql_example(PDO $pdo, string $key) {
    $examples = get_sql_examples();
    if (!isset($examples[$key])) throw new InvalidArgumentException("Unknown SQL example: $key");
    $sql = $examples[$key];
    // Only run SELECTs automatically; DDL/ALTER must be run with care
    $first = strtoupper(trim(substr($sql,0,10)));
    if (strpos($first, 'SELECT') === 0 || strpos($first, 'WITH') === 0) {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    throw new RuntimeException('Only SELECT examples can be executed via run_sql_example() for safety.');
}

?>
