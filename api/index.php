<?php
/**
 * EVEE CRM - REST API router
 * ---------------------------------
 * GET  /api/index.php/contacts                 -> list (filters: search, tag, type, lead, sort)
 * GET  /api/index.php/contacts/{id}            -> single contact with tags
 * POST /api/index.php/contacts                 -> create contact
 * POST /api/index.php/contacts/bulk-delete     -> delete many ({ "ids": [1,2] })
 * DELETE /api/index.php/contacts/{id}          -> delete one
 *  GET  /api/index.php/leads                    -> LEADS ONLY (the "find leads" shortcut)
 *  GET  /api/index.php/tags                     -> list all tags
 *  GET  /api/index.php/campaigns?status=active  -> list campaigns (status: active|paused|canceled|finished)
 *  GET  /api/index.php/workflows?status=active  -> list workflows (status: active|finished)
 *  GET  /api/index.php/staff                    -> list staff users
 *  POST /api/index.php/staff                    -> create staff user
 *  PUT  /api/index.php/staff/{id}               -> update staff user
 *  DELETE /api/index.php/staff/{id}             -> delete staff user
 *  PUT  /api/index.php/contacts/{id}            -> update contact (e.g. assigned_to)
 *
 * Full URL example:  http://localhost/Evee/api/index.php/contacts?search=faiz
 */

declare(strict_types=1);

require __DIR__ . '/config.php';

cors();

/** Parse request path relative to this script, e.g. ["contacts", "5"]. */
function route_parts(): array
{
    $script = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '';
    $base = parse_url($_SERVER['SCRIPT_NAME'], PHP_URL_PATH) ?: '';
    $path = substr($script, strlen($base));
    $parts = array_values(array_filter(explode('/', $path), fn($p) => $p !== ''));
    return $parts;
}

function to_int(string $v): int
{
    return (int)$v;
}

function build_where(array $filters, array &$params): string
{
    $clauses = [];

    if (!empty($filters['search'])) {
        $term = '%' . $filters['search'] . '%';
        // PDO named placeholders must be unique, so repeat the term across 5 params.
        $searchCols = ['name', 'phone', 'email', 'business_name', 'tags'];
        $pats = [];
        foreach ($searchCols as $i => $col) {
            $pname = ':search' . $i;
            $params[$pname] = $term;
            $pats[] = "$col LIKE $pname";
        }
        $clauses[] = '(' . implode(' OR ', $pats) . ')';
    }
    if (!empty($filters['tag'])) {
        $params[':tag'] = $filters['tag'];
        $clauses[] = 'FIND_IN_SET(:tag, tags)';
    }
    if (!empty($filters['type'])) {
        $params[':type'] = $filters['type'];
        $clauses[] = 'contact_type = :type';
    }
    if (isset($filters['lead'])) {
        $params[':lead'] = to_int($filters['lead']);
        $clauses[] = 'is_lead = :lead';
    }
    if (!empty($filters['restrict_to'])) {
        // Restricted users only see data assigned to them or that they follow.
        // (Two distinct placeholders: MySQL native prepares reject reused names.)
        $rid = to_int((string)$filters['restrict_to']);
        $params[':rid_owner'] = $rid;
        $params[':rid_follower'] = $rid;
        $clauses[] = '(assigned_to = :rid_owner OR id IN (SELECT contact_id FROM contact_followers WHERE staff_id = :rid_follower))';
    }

    return $clauses ? (' WHERE ' . implode(' AND ', $clauses)) : '';
}

function order_clause(array $filters): string
{
    $allowed = ['name', 'phone', 'email', 'business_name', 'created_at', 'last_activity_at'];
    $sort = $filters['sort'] ?? 'created_at';
    $sort = in_array($sort, $allowed, true) ? $sort : 'created_at';
    $dir = strtolower($filters['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    return sprintf(' ORDER BY %s %s', $sort, $dir);
}

/** List contacts (from the aggregated view). */
function list_contacts(array $filters): void
{
    $params = [];
    $where = build_where($filters, $params);
    $order = order_clause($filters);

    $stmt = db()->prepare('SELECT * FROM v_contacts_with_tags' . $where . $order);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['tags'] = $row['tags'] !== '' ? explode(',', $row['tags']) : [];
        $row['tag_ids'] = $row['tag_ids'] !== '' ? array_map('to_int', explode(',', $row['tag_ids'])) : [];
        $row['custom_fields'] = decode_custom_fields($row['custom_fields'] ?? null);
        $row['followers'] = contact_followers((int)$row['id']);
    }

    respond(['data' => $rows, 'count' => count($rows)]);
}

/** Load the follower staff summaries for a contact. */
function contact_followers(int $contactId): array
{
    $rows = db()->prepare(
        'SELECT s.id, s.first_name, s.last_name, s.full_name, s.user_type, s.avatar_data
           FROM contact_followers cf
           JOIN staff_users s ON s.id = cf.staff_id
          WHERE cf.contact_id = :id
          ORDER BY s.full_name'
    );
    $rows->execute([':id' => $contactId]);
    return $rows->fetchAll();
}

/** Single contact with tags. */
function get_contact(int $id): void
{
    $stmt = db()->prepare('SELECT * FROM v_contacts_with_tags WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if (!$row) fail('Contact not found', 404);

    $row['tags'] = $row['tags'] !== '' ? explode(',', $row['tags']) : [];
    $row['tag_ids'] = $row['tag_ids'] !== '' ? array_map('to_int', explode(',', $row['tag_ids'])) : [];
    $row['custom_fields'] = decode_custom_fields($row['custom_fields'] ?? null);
    $row['followers'] = contact_followers((int)$id);
    respond(['data' => $row]);
}

/** Find-or-create a tag, return its id. */
function ensure_tag(PDO $pdo, string $tagName): int
{
    $name = trim($tagName);
    if ($name === '') return 0;

    $stmt = $pdo->prepare('SELECT id FROM tags WHERE name = :name');
    $stmt->execute([':name' => $name]);
    $id = $stmt->fetchColumn();

    if ($id !== false) return (int)$id;

    $stmt = $pdo->prepare('INSERT INTO tags (name) VALUES (:name)');
    $stmt->execute([':name' => $name]);
    return (int)$pdo->lastInsertId();
}

/** Normalize a custom_fields value (array or JSON string) into a JSON text column value. */
function normalize_custom_fields($value): ?string
{
    if (is_array($value)) {
        return encode_json_field($value);
    }
    if (is_string($value) && $value !== '') {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $value;
    }
    return null;
}

/** Decode the custom_fields JSON column into a PHP array for the API response. */
function decode_custom_fields(?string $raw): array
{
    $decoded = decode_json_field($raw);
    return is_array($decoded) ? $decoded : [];
}

/** Create a contact (accepts first_name/last_name or a combined name). */
function create_contact(array $body): void
{
    $firstName = normalize_optional($body['first_name'] ?? null);
    $lastName = normalize_optional($body['last_name'] ?? null);

    // Fallback: accept a combined "name" and split on first space.
    if ($firstName === null && !empty($body['name'])) {
        $name = trim((string)$body['name']);
        $space = strpos($name, ' ');
        if ($space === false) {
            $firstName = $name;
        } else {
            $firstName = substr($name, 0, $space);
            $lastName = substr($name, $space + 1);
        }
    }

    if ($firstName === null || $firstName === '') {
        fail('First name is required');
    }

    // last_name is NOT NULL in the schema; coerce null/empty to ''.
    $lastName = $lastName ?? '';

    $phone = normalize_optional($body['phone'] ?? null);
    $email = normalize_optional($body['email'] ?? null);
    $business = normalize_optional($body['business_name'] ?? null);
    $contactType = in_array($body['contact_type'] ?? '', ['Lead', 'Customer', 'Vendor', 'Partner'], true)
        ? $body['contact_type'] : '';
    $avatarColor = normalize_optional($body['avatar_color'] ?? null) ?? 'bg-emerald-200 text-emerald-800';
    $avatarData = normalize_optional($body['avatar_data'] ?? null);
    $customFields = normalize_custom_fields($body['custom_fields'] ?? null);

    $tagInput = array_values(array_filter(
        array_map(fn($t) => trim((string)$t), (array)($body['tags'] ?? [])),
        fn($t) => $t !== ''
    ));

    $isLead = $contactType === 'Lead'
        || in_array('warm lead', $tagInput, true)
        || in_array('hot lead', $tagInput, true)
        || in_array('cold lead', $tagInput, true);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Dedupe on email OR phone: if an existing contact already carries
        // either value, update it instead of failing, so form re-submissions
        // never hard-block with a "duplicate" error.
        $existingId = 0;
        if ($email !== null || $phone !== null) {
            $dupSql = 'SELECT id FROM contacts WHERE 1=0';
            $dupParams = [];
            if ($email !== null) {
                $dupSql .= ' OR email = :dup_email';
                $dupParams[':dup_email'] = $email;
            }
            if ($phone !== null) {
                $dupSql .= ' OR phone = :dup_phone';
                $dupParams[':dup_phone'] = $phone;
            }
            $dupStmt = $pdo->prepare($dupSql);
            $dupStmt->execute($dupParams);
            $dupId = $dupStmt->fetchColumn();
            if ($dupId !== false) $existingId = (int)$dupId;
        }

        if ($existingId > 0) {
            // Guard: if the incoming email/phone is already owned by a DIFFERENT
            // contact, skip overwriting that field (keep the original value).
            if ($email !== null) {
                $owner = $pdo->prepare('SELECT id FROM contacts WHERE email = :email AND id <> :id LIMIT 1');
                $owner->execute([':email' => $email, ':id' => $existingId]);
                if ($owner->fetchColumn() !== false) $email = null;
            }
            if ($phone !== null) {
                $owner = $pdo->prepare('SELECT id FROM contacts WHERE phone = :phone AND id <> :id LIMIT 1');
                $owner->execute([':phone' => $phone, ':id' => $existingId]);
                if ($owner->fetchColumn() !== false) $phone = null;
            }

            $updates = [];
            $params = [':id' => $existingId];
            if ($firstName !== null) { $updates[] = 'first_name = :first_name'; $params[':first_name'] = $firstName; }
            if ($lastName !== null) { $updates[] = 'last_name = :last_name'; $params[':last_name'] = $lastName; }
            if ($phone !== null) { $updates[] = 'phone = :phone'; $params[':phone'] = $phone; }
            if ($email !== null) { $updates[] = 'email = :email'; $params[':email'] = $email; }
            if ($business !== null) { $updates[] = 'business_name = :business_name'; $params[':business_name'] = $business; }
            if ($contactType !== '') { $updates[] = 'contact_type = :contact_type'; $params[':contact_type'] = $contactType; }
            if ($isLead) { $updates[] = 'is_lead = :is_lead'; $params[':is_lead'] = 1; }
            if ($avatarData !== null) { $updates[] = 'avatar_data = :avatar_data'; $params[':avatar_data'] = $avatarData; }
            if ($customFields !== null) { $updates[] = 'custom_fields = :custom_fields'; $params[':custom_fields'] = $customFields; }
            $updates[] = 'last_activity_at = NOW()';
            if ($updates) {
                $pdo->prepare('UPDATE contacts SET ' . implode(', ', $updates) . ' WHERE id = :id')
                    ->execute($params);
            }

            foreach ($tagInput as $tagName) {
                $tagId = ensure_tag($pdo, $tagName);
                if ($tagId > 0) {
                    $pdo->prepare('INSERT IGNORE INTO contact_tags (contact_id, tag_id) VALUES (:c, :t)')
                        ->execute([':c' => $existingId, ':t' => $tagId]);
                }
            }

            // This lead already existed (matched by email/phone) and re-submitted
            // the form — tag it so the repeat submission is visible at a glance.
            $repeatTagId = ensure_tag($pdo, 'Form Re-submitted');
            if ($repeatTagId > 0) {
                $pdo->prepare('INSERT IGNORE INTO contact_tags (contact_id, tag_id) VALUES (:c, :t)')
                    ->execute([':c' => $existingId, ':t' => $repeatTagId]);
            }

            $pdo->commit();
            respond(['data' => ['id' => $existingId], 'message' => 'Contact already exists; updated'], 200);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO contacts (first_name, last_name, phone, email, business_name,
                                   contact_type, is_lead, avatar_color, avatar_data, custom_fields)
             VALUES (:fn, :ln, :phone, :email, :biz, :type, :lead, :color, :avatar, :custom)'
        );
        $stmt->execute([
            ':fn' => $firstName,
            ':ln' => $lastName,
            ':phone' => $phone,
            ':email' => $email,
            ':biz' => $business,
            ':type' => $contactType,
            ':lead' => $isLead ? 1 : 0,
            ':color' => $avatarColor,
            ':avatar' => $avatarData,
            ':custom' => $customFields,
        ]);

        $contactId = (int)$pdo->lastInsertId();

        foreach ($tagInput as $tagName) {
            $tagId = ensure_tag($pdo, $tagName);
            if ($tagId > 0) {
                $pdo->prepare('INSERT IGNORE INTO contact_tags (contact_id, tag_id) VALUES (:c, :t)')
                    ->execute([':c' => $contactId, ':t' => $tagId]);
            }
        }

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        fail('Database error: ' . $e->getMessage(), 500);
    }

    respond(['data' => ['id' => $contactId], 'message' => 'Contact created'], 201);
}

/** Delete one contact. */
function delete_contact(int $id): void
{
    $stmt = db()->prepare('DELETE FROM contacts WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) fail('Contact not found', 404);
    prune_smart_lists_when_empty();
    respond(['message' => 'Contact deleted']);
}

/** Delete many contacts. */
function bulk_delete(array $body): void
{
    $ids = array_values(array_filter(array_map('to_int', (array)($body['ids'] ?? [])), fn($i) => $i > 0));
    if (!$ids) fail('No ids provided');

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("DELETE FROM contacts WHERE id IN ($placeholders)");
    $stmt->execute($ids);

    prune_smart_lists_when_empty();

    respond(['message' => 'Deleted ' . $stmt->rowCount() . ' contact(s)']);
}

/**
 * When the very last contact is deleted, every smart list becomes meaningless
 * (there is nothing left to group), so remove them all.
 */
function prune_smart_lists_when_empty(): void
{
    $count = (int)db()->query('SELECT COUNT(*) FROM contacts')->fetchColumn();
    if ($count === 0) {
        db()->exec('DELETE FROM smart_lists');
    }
}

/** List all tags. */
function list_tags(): void
{
    $rows = db()->query('SELECT id, name, color FROM tags ORDER BY name')->fetchAll();
    respond(['data' => $rows]);
}

/** List campaigns, optionally filtered by status (active|paused|canceled|finished). */
function list_campaigns(array $filters): void
{
    $allowed = ['active', 'paused', 'canceled', 'finished'];
    $status = strtolower((string)($filters['status'] ?? ''));

    $where = '';
    $params = [];
    if ($status !== '' && in_array($status, $allowed, true)) {
        $where = ' WHERE status = :status';
        $params[':status'] = $status;
    }

    try {
        $stmt = db()->prepare('SELECT id, name, status, description, created_at FROM campaigns' . $where . ' ORDER BY name');
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Table not created yet -> treat as no campaigns rather than a hard error.
        $rows = [];
    }

    respond(['data' => $rows, 'count' => count($rows)]);
}

/** List workflows, optionally filtered by status (active|finished). */
function list_workflows(array $filters): void
{
    $allowed = ['active', 'finished'];
    $status = strtolower((string)($filters['status'] ?? ''));

    $where = '';
    $params = [];
    if ($status !== '' && in_array($status, $allowed, true)) {
        $where = ' WHERE status = :status';
        $params[':status'] = $status;
    }

    try {
        $stmt = db()->prepare('SELECT id, name, status, description, created_at FROM workflows' . $where . ' ORDER BY name');
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Table not created yet -> treat as no workflows rather than a hard error.
        $rows = [];
    }

    respond(['data' => $rows, 'count' => count($rows)]);
}

/** LEADS ONLY — quick way to find leads. */
function list_leads(array $filters): void
{
    $params = [];
    $clauses = [];

    if (!empty($filters['search'])) {
        $term = '%' . $filters['search'] . '%';
        $searchCols = ['name', 'phone', 'email', 'business_name', 'tags'];
        $pats = [];
        foreach ($searchCols as $i => $col) {
            $pname = ':search' . $i;
            $params[$pname] = $term;
            $pats[] = "$col LIKE $pname";
        }
        $clauses[] = '(' . implode(' OR ', $pats) . ')';
    }
    if (!empty($filters['restrict_to'])) {
        $rid = to_int((string)$filters['restrict_to']);
        $params[':rid_owner'] = $rid;
        $params[':rid_follower'] = $rid;
        $clauses[] = '(assigned_to = :rid_owner OR id IN (SELECT contact_id FROM contact_followers WHERE staff_id = :rid_follower))';
    }
    $where = $clauses ? (' WHERE ' . implode(' AND ', $clauses)) : '';
    $order = order_clause($filters);

    $stmt = db()->prepare('SELECT * FROM v_leads' . $where . $order);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['tags'] = $row['tags'] !== '' ? explode(',', $row['tags']) : [];
        $row['tag_ids'] = $row['tag_ids'] !== '' ? array_map('to_int', explode(',', $row['tag_ids'])) : [];
        $row['custom_fields'] = decode_custom_fields($row['custom_fields'] ?? null);
    }

    respond(['data' => $rows, 'count' => count($rows)]);
}

/* ------------------ CONTACT SUB-RESOURCES ------------------ */

function ensure_contact_exists(int $contactId): void
{
    $stmt = db()->prepare('SELECT id FROM contacts WHERE id = :id');
    $stmt->execute([':id' => $contactId]);
    if (!$stmt->fetch()) fail('Contact not found', 404);
}

function list_opportunities(int $contactId): void
{
    ensure_contact_exists($contactId);
    $stmt = db()->prepare('SELECT * FROM opportunities WHERE contact_id = :id ORDER BY created_at DESC');
    $stmt->execute([':id' => $contactId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['tags'] = $row['tags'] !== '' && $row['tags'] !== null
            ? array_values(array_filter(array_map('trim', explode(',', $row['tags']))))
            : [];
    }

    respond(['data' => $rows, 'count' => count($rows)]);
}

function create_opportunity(int $contactId, array $body): void
{
    ensure_contact_exists($contactId);

    $tagsInput = array_map('trim', (array)($body['tags'] ?? []));
    $tagsInput = array_values(array_filter($tagsInput, fn($t) => $t !== ''));

    $stmt = db()->prepare(
        'INSERT INTO opportunities (contact_id, name, pipeline, stage, status, value, business_name,
                                    source, expected_close_date, tags)
         VALUES (:c, :name, :pipeline, :stage, :status, :value, :biz, :source, :close, :tags)'
    );
    $stmt->execute([
        ':c' => $contactId,
        ':name' => normalize_optional($body['name'] ?? null) ?? 'New Opportunity',
        ':pipeline' => normalize_optional($body['pipeline'] ?? null) ?? 'Marketing Pipeline',
        ':stage' => normalize_optional($body['stage'] ?? null) ?? 'New Lead',
        ':status' => normalize_optional($body['status'] ?? null) ?? 'Open',
        ':value' => normalize_optional($body['value'] ?? null) ?? 'Rs 0',
        ':biz' => normalize_optional($body['business_name'] ?? null) ?? '',
        ':source' => normalize_optional($body['source'] ?? null) ?? '',
        ':close' => normalize_optional($body['expected_close_date'] ?? null) ?? '',
        ':tags' => $tagsInput ? implode(',', $tagsInput) : '',
    ]);
    respond(['data' => ['id' => (int)db()->lastInsertId()], 'message' => 'Opportunity created'], 201);
}

function delete_opportunity(int $id): void
{
    $stmt = db()->prepare('DELETE FROM opportunities WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) fail('Opportunity not found', 404);
    respond(['message' => 'Opportunity deleted']);
}

function list_tasks(int $contactId): void
{
    ensure_contact_exists($contactId);
    $stmt = db()->prepare('SELECT * FROM tasks WHERE contact_id = :id ORDER BY created_at DESC');
    $stmt->execute([':id' => $contactId]);
    $rows = $stmt->fetchAll();
    respond(['data' => $rows, 'count' => count($rows)]);
}

function create_task(int $contactId, array $body): void
{
    ensure_contact_exists($contactId);
    $stmt = db()->prepare(
        'INSERT INTO tasks (contact_id, title, status, due_date) VALUES (:c, :title, :status, :due)'
    );
    $stmt->execute([
        ':c' => $contactId,
        ':title' => normalize_optional($body['title'] ?? null) ?? 'New task',
        ':status' => in_array($body['status'] ?? '', ['Pending', 'Done'], true) ? $body['status'] : 'Pending',
        ':due' => normalize_optional($body['due_date'] ?? null) ?? '',
    ]);
    respond(['data' => ['id' => (int)db()->lastInsertId()], 'message' => 'Task created'], 201);
}

function delete_task(int $id): void
{
    $stmt = db()->prepare('DELETE FROM tasks WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) fail('Task not found', 404);
    respond(['message' => 'Task deleted']);
}

function list_notes(int $contactId): void
{
    ensure_contact_exists($contactId);
    $stmt = db()->prepare('SELECT * FROM notes WHERE contact_id = :id ORDER BY created_at DESC');
    $stmt->execute([':id' => $contactId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['attachments'] = $row['attachments'] !== '' && $row['attachments'] !== null
            ? array_values(array_filter(array_map('trim', explode(',', $row['attachments']))))
            : [];
    }

    respond(['data' => $rows, 'count' => count($rows)]);
}

function create_note(int $contactId, array $body): void
{
    ensure_contact_exists($contactId);

    $attachments = array_values(array_filter(
        array_map(fn($a) => trim((string)$a), (array)($body['attachments'] ?? [])),
        fn($a) => $a !== ''
    ));

    $stmt = db()->prepare(
        'INSERT INTO notes (contact_id, title, content, author, note_color, attachments, associated_to)
         VALUES (:c, :title, :content, :author, :color, :attachments, :assoc)'
    );
    $stmt->execute([
        ':c' => $contactId,
        ':title' => normalize_optional($body['title'] ?? null) ?? 'Note',
        ':content' => normalize_optional($body['content'] ?? null) ?? '',
        ':author' => normalize_optional($body['author'] ?? null) ?? 'Asad B Zaman',
        ':color' => normalize_optional($body['note_color'] ?? null) ?? '',
        ':attachments' => $attachments ? implode(',', $attachments) : '',
        ':assoc' => normalize_optional($body['associated_to'] ?? null) ?? '',
    ]);
    respond(['data' => ['id' => (int)db()->lastInsertId()], 'message' => 'Note created'], 201);
}

function delete_note(int $id): void
{
    $stmt = db()->prepare('DELETE FROM notes WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) fail('Note not found', 404);
    respond(['message' => 'Note deleted']);
}

function list_appointments(int $contactId): void
{
    ensure_contact_exists($contactId);
    $stmt = db()->prepare('SELECT * FROM appointments WHERE contact_id = :id ORDER BY created_at DESC');
    $stmt->execute([':id' => $contactId]);
    $rows = $stmt->fetchAll();
    respond(['data' => $rows, 'count' => count($rows)]);
}

function create_appointment(int $contactId, array $body): void
{
    ensure_contact_exists($contactId);
    $stmt = db()->prepare(
        'INSERT INTO appointments (contact_id, title, calendar, host, date, start_time, end_time,
                                   location, status, notes, category)
         VALUES (:c, :title, :cal, :host, :date, :st, :et, :loc, :status, :notes, :cat)'
    );
    $stmt->execute([
        ':c' => $contactId,
        ':title' => normalize_optional($body['title'] ?? null) ?? 'Appointment',
        ':cal' => normalize_optional($body['calendar'] ?? null) ?? '',
        ':host' => normalize_optional($body['host'] ?? null) ?? '',
        ':date' => normalize_optional($body['date'] ?? null) ?? '',
        ':st' => normalize_optional($body['start_time'] ?? null) ?? '',
        ':et' => normalize_optional($body['end_time'] ?? null) ?? '',
        ':loc' => normalize_optional($body['location'] ?? null) ?? '',
        ':status' => normalize_optional($body['status'] ?? null) ?? 'Completed',
        ':notes' => normalize_optional($body['notes'] ?? null),
        ':cat' => ($body['category'] ?? 'past') === 'upcoming' ? 'upcoming' : 'past',
    ]);
    respond(['data' => ['id' => (int)db()->lastInsertId()], 'message' => 'Appointment created'], 201);
}

function delete_appointment(int $id): void
{
    $stmt = db()->prepare('DELETE FROM appointments WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) fail('Appointment not found', 404);
    respond(['message' => 'Appointment deleted']);
}

/* ------------------- STAFF USERS (My Staff) ------------------- */

/** Decode a stored JSON text column into a PHP array (or [] when empty). */
function decode_json_field(?string $raw): array
{
    if ($raw === null || $raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/** Encode a PHP structure to JSON text (or null when empty). */
function encode_json_field($value): ?string
{
    if ($value === null) return null;
    if (is_array($value) && count($value) === 0) return null;
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json === false ? null : $json;
}

function list_staff(): void
{
    $rows = db()->query('SELECT * FROM staff_users ORDER BY full_name')->fetchAll();

    $payload = array_map('staff_payload', $rows);

    respond(['data' => $payload, 'count' => count($payload)]);
}

/** Common INSERT/UPDATE for a staff user; returns the staff id. */
function upsert_staff(array $body, ?int $existingId = null): int
{
    $firstName = normalize_optional($body['first_name'] ?? null);
    if ($firstName === null || $firstName === '') {
        fail('First name is required');
    }

    $phone = normalize_optional($body['phone'] ?? null);
    $email = normalize_optional($body['email'] ?? null);
    $lastName = normalize_optional($body['last_name'] ?? null);
    $extension = normalize_optional($body['extension'] ?? null);
    $calendar = normalize_optional($body['calendar'] ?? null);
    $systemId = normalize_optional($body['system_id'] ?? null);
    $signature = $body['signature'] ?? null;
    $avatarData = $body['avatar_data'] ?? null; // base64 data URI
    $restrictData = !empty($body['restrict_data']) ? 1 : 0;
    $userType = in_array($body['user_type'] ?? '', ['Admin', 'Dealer', 'Follower'], true) ? $body['user_type'] : 'Follower';
    // A Follower belongs to the Dealer who created them. Anyone else gets NULL.
    $managerId = null;
    if (isset($body['manager_id'])) {
        $managerId = $body['manager_id'] === null ? null : to_int((string)$body['manager_id']);
        if ($managerId !== null && $managerId <= 0) $managerId = null;
    }
    $password = isset($body['password']) && $body['password'] !== ''
        ? hash_password((string)$body['password'])
        : null;

    $pdo = db();
    $fullName = trim($firstName . ' ' . ($lastName ?? ''));

    if ($existingId !== null) {
        // manager_id is only touched when explicitly provided so a follower's
        // own profile edit never detaches them from their dealer.
        $hasManager = array_key_exists('manager_id', $body);
        $stmt = $pdo->prepare(
            'UPDATE staff_users SET first_name = :fn, last_name = :ln, full_name = :full, email = :email, phone = :phone,
                    extension = :ext, calendar = :cal, system_id = :sid, signature = :sig,
                    avatar_data = :avatar, restrict_data = :rd, user_type = :type'
                    . ($hasManager ? ', manager_id = :mgr' : '') .
                    ', call_voicemail = :cv, availability = :av, calendar_config = :cc, permissions = :perm
                    ' . ($password !== null ? ', password = :password' : '') . '
             WHERE id = :id'
        );
        $params = [
            ':fn' => $firstName,
            ':ln' => $lastName,
            ':full' => $fullName,
            ':email' => $email,
            ':phone' => $phone,
            ':ext' => $extension,
            ':cal' => $calendar,
            ':sid' => $systemId,
            ':sig' => $signature,
            ':avatar' => $avatarData,
            ':rd' => $restrictData,
            ':type' => $userType,
            ':cv' => encode_json_field($body['call_voicemail'] ?? null),
            ':av' => encode_json_field($body['availability'] ?? null),
            ':cc' => encode_json_field($body['calendar_config'] ?? null),
            ':perm' => encode_json_field($body['permissions'] ?? null),
        ];
        if ($hasManager) $params[':mgr'] = $managerId;
        if ($password !== null) $params[':password'] = $password;
        $params[':id'] = $existingId;
        $stmt->execute($params);
        if ($stmt->rowCount() === 0) {
            // Still succeeded if no columns changed, so verify existence.
            $check = $pdo->prepare('SELECT id FROM staff_users WHERE id = :id');
            $check->execute([':id' => $existingId]);
            if (!$check->fetch()) fail('Staff user not found', 404);
        }
        return $existingId;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO staff_users (first_name, last_name, full_name, email, phone, extension, calendar, system_id,
                                  signature, avatar_data, restrict_data, user_type, manager_id,
                                  call_voicemail, availability, calendar_config, permissions'
                                  . ($password !== null ? ', password' : '') . ')
         VALUES (:fn, :ln, :full, :email, :phone, :ext, :cal, :sid, :sig, :avatar, :rd, :type, :mgr,
                 :cv, :av, :cc, :perm'
                 . ($password !== null ? ', :password' : '') . ')'
    );
    $insertParams = [
        ':fn' => $firstName,
        ':ln' => $lastName,
        ':full' => $fullName,
        ':email' => $email,
        ':phone' => $phone,
        ':ext' => $extension,
        ':cal' => $calendar,
        ':sid' => $systemId,
        ':sig' => $signature,
        ':avatar' => $avatarData,
        ':rd' => $restrictData,
        ':type' => $userType,
        ':mgr' => $managerId,
        ':cv' => encode_json_field($body['call_voicemail'] ?? null),
        ':av' => encode_json_field($body['availability'] ?? null),
        ':cc' => encode_json_field($body['calendar_config'] ?? null),
        ':perm' => encode_json_field($body['permissions'] ?? null),
    ];
    if ($password !== null) $insertParams[':password'] = $password;
    $stmt->execute($insertParams);
    return (int)$pdo->lastInsertId();
}

function create_staff(array $body): void
{
    try {
        $id = upsert_staff($body, null);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') fail('Duplicate email already exists', 409);
        fail('Database error: ' . $e->getMessage(), 500);
    }
    respond(['data' => ['id' => $id], 'message' => 'Staff user created'], 201);
}

function update_staff(int $id, array $body): void
{
    try {
        $updated = upsert_staff($body, $id);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') fail('Duplicate email already exists', 409);
        fail('Database error: ' . $e->getMessage(), 500);
    }
    respond(['data' => ['id' => $updated], 'message' => 'Staff user updated']);
}

function delete_staff(int $id): void
{
    $stmt = db()->prepare('DELETE FROM staff_users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) fail('Staff user not found', 404);
    respond(['message' => 'Staff user deleted']);
}

/** Update a contact: mainly for assigning a staff owner (assigned_to). */
function update_contact(int $id, array $body): void
{
    ensure_contact_exists($id);

    // Verify assigned staff exists when provided.
    $assignedTo = null;
    if (array_key_exists('assigned_to', $body)) {
        $assignedTo = $body['assigned_to'] === null ? null : to_int((string)$body['assigned_to']);
        if ($assignedTo !== null && $assignedTo > 0) {
            $check = db()->prepare('SELECT id FROM staff_users WHERE id = :id');
            $check->execute([':id' => $assignedTo]);
            if (!$check->fetch()) fail('Assigned staff user not found', 404);
        }
    }

    $allowed = ['first_name', 'last_name', 'phone', 'email', 'business_name', 'contact_type', 'notes', 'avatar_data'];
    $sets = [];
    $params = [':id' => $id];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $body)) {
            $value = normalize_optional($body[$field]);
            if ($field === 'contact_type') {
                $value = in_array($value ?? '', ['Lead', 'Customer', 'Vendor', 'Partner'], true) ? $value : '';
            }
            $sets[] = "$field = :$field";
            $params[":$field"] = $value;
        }
    }

    if (array_key_exists('custom_fields', $body)) {
        $sets[] = 'custom_fields = :custom_fields';
        $params[':custom_fields'] = normalize_custom_fields($body['custom_fields']);
    }

    // Replace the full tag set when the request includes a "tags" list.
    if (array_key_exists('tags', $body)) {
        $tagInput = array_values(array_filter(
            array_map(fn($t) => trim((string)$t), (array)$body['tags']),
            fn($t) => $t !== ''
        ));
        $pdo = db();
        $pdo->prepare('DELETE FROM contact_tags WHERE contact_id = :id')->execute([':id' => $id]);
        foreach ($tagInput as $tagName) {
            $tagId = ensure_tag($pdo, $tagName);
            if ($tagId > 0) {
                $pdo->prepare('INSERT IGNORE INTO contact_tags (contact_id, tag_id) VALUES (:c, :t)')
                    ->execute([':c' => $id, ':t' => $tagId]);
            }
        }
        // Keep is_lead consistent with the new tag set / contact type.
        $lead = ($body['contact_type'] ?? '') === 'Lead'
            || in_array('warm lead', $tagInput, true)
            || in_array('hot lead', $tagInput, true)
            || in_array('cold lead', $tagInput, true);
        $sets[] = 'is_lead = :is_lead';
        $params[':is_lead'] = $lead ? 1 : 0;
    }

    if ($assignedTo !== null || array_key_exists('assigned_to', $body)) {
        $sets[] = 'assigned_to = :assigned_to';
        $params[':assigned_to'] = $assignedTo;
    }

    if (!$sets) fail('No fields to update');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Capture the previous owner + contact name so we only notify on change.
        $prev = $pdo->prepare('SELECT assigned_to, full_name FROM contacts WHERE id = :id');
        $prev->execute([':id' => $id]);
        $prevRow = $prev->fetch();
        $prevOwner = $prevRow ? (int)($prevRow['assigned_to'] ?? 0) : 0;
        $contactName = $prevRow['full_name'] ?? 'Contact';

        $stmt = $pdo->prepare('UPDATE contacts SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);

        // Mutual exclusion: the new owner can no longer be a follower.
        if ($assignedTo !== null && $assignedTo > 0) {
            $pdo->prepare('DELETE FROM contact_followers WHERE contact_id = :c AND staff_id = :s')
                ->execute([':c' => $id, ':s' => $assignedTo]);
        }

        $pdo->commit();

        // Notify the new owner only when the assignment actually changed.
        if ($assignedTo !== null && $assignedTo > 0 && $assignedTo !== $prevOwner) {
            notify_staff(
                $assignedTo,
                $id,
                'lead_assigned',
                'New lead assigned to you',
                $contactName . ' has been assigned to you.'
            );
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        fail('Database error: ' . $e->getMessage(), 500);
    }

    respond(['data' => ['id' => $id], 'message' => 'Contact updated']);
}

/** List the staff users following a contact. */
function list_followers(int $contactId): void
{
    ensure_contact_exists($contactId);
    $rows = db()->prepare(
        'SELECT s.id, s.first_name, s.last_name, s.full_name, s.user_type, s.avatar_data
           FROM contact_followers cf
           JOIN staff_users s ON s.id = cf.staff_id
          WHERE cf.contact_id = :id
          ORDER BY s.full_name'
    );
    $rows->execute([':id' => $contactId]);
    respond(['data' => $rows->fetchAll(), 'count' => $rows->rowCount()]);
}

/** Add a follower. Owner and follower are mutually exclusive: a follower can
 *  never be the assigned owner, and the assigned owner can never be a follower. */
function add_follower(int $contactId, array $body): void
{
    ensure_contact_exists($contactId);
    $staffId = to_int((string)($body['staff_id'] ?? 0));
    if ($staffId <= 0) fail('staff_id is required');

    $check = db()->prepare('SELECT id FROM staff_users WHERE id = :id');
    $check->execute([':id' => $staffId]);
    if (!$check->fetch()) fail('Staff user not found', 404);

    $pdo = db();
    $nmStmt = $pdo->prepare('SELECT full_name FROM contacts WHERE id = :id');
    $nmStmt->execute([':id' => $contactId]);
    $nmRow = $nmStmt->fetch();
    $contactName = $nmRow['full_name'] ?? 'Contact';

    $pdo->beginTransaction();
    try {
        // Mutual exclusion: if this staff is the assigned owner, remove that role.
        $pdo->prepare('UPDATE contacts SET assigned_to = NULL WHERE id = :cid AND assigned_to = :sid')
            ->execute([':cid' => $contactId, ':sid' => $staffId]);

        $stmt = $pdo->prepare('INSERT IGNORE INTO contact_followers (contact_id, staff_id) VALUES (:c, :s)');
        $stmt->execute([':c' => $contactId, ':s' => $staffId]);
        $added = $stmt->rowCount() > 0;
        $pdo->commit();

        if ($added) {
            notify_staff(
                $staffId,
                $contactId,
                'follower_added',
                'You are now following a contact',
                'You have been added as a follower of ' . $contactName . '.'
            );
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        fail('Database error: ' . $e->getMessage(), 500);
    }

    respond(['message' => 'Follower added'], 201);
}

/** Remove a follower. */
function remove_follower(int $contactId, int $staffId): void
{
    ensure_contact_exists($contactId);
    $stmt = db()->prepare('DELETE FROM contact_followers WHERE contact_id = :c AND staff_id = :s');
    $stmt->execute([':c' => $contactId, ':s' => $staffId]);
    if ($stmt->rowCount() === 0) fail('Follower not found', 404);
    respond(['message' => 'Follower removed']);
}

/** List persisted activity entries for a contact. */
function list_activities(int $contactId): void
{
    ensure_contact_exists($contactId);
    $rows = db()->prepare(
        'SELECT id, type, title, detail, created_at
           FROM contact_activities
          WHERE contact_id = :id
          ORDER BY created_at DESC, id DESC'
    );
    $rows->execute([':id' => $contactId]);
    respond(['data' => $rows->fetchAll(), 'count' => $rows->rowCount()]);
}

/** Persist an activity entry for a contact (date/time + URL detail). */
function create_activity(int $contactId, array $body): void
{
    ensure_contact_exists($contactId);
    $type = normalize_optional($body['type'] ?? null) ?? 'contact';
    $title = normalize_optional($body['title'] ?? null);
    $detail = normalize_optional($body['detail'] ?? null);
    if ($title === null) fail('title is required');

    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO contact_activities (contact_id, type, title, detail, created_at)
         VALUES (:c, :type, :title, :detail, NOW())'
    );
    $stmt->execute([':c' => $contactId, ':type' => $type, ':title' => $title, ':detail' => $detail]);

    respond(['data' => ['id' => (int)$pdo->lastInsertId()], 'message' => 'Activity logged']);
}

/* ----------------------- AUTH & NOTIFICATIONS ----------------------- */

/** Shape a staff row for API responses: decode JSON fields, hide password. */
function staff_payload(array $row): array
{
    unset($row['password']);
    $row['call_voicemail'] = decode_json_field($row['call_voicemail'] ?? null);
    $row['availability'] = decode_json_field($row['availability'] ?? null);
    $row['calendar_config'] = decode_json_field($row['calendar_config'] ?? null);
    $row['permissions'] = decode_json_field($row['permissions'] ?? null);
    return $row;
}

/** POST /auth/login  { email, password } -> the staff user or 401. */
function login(array $body): void
{
    $email = normalize_optional($body['email'] ?? null);
    $password = (string)($body['password'] ?? '');
    if ($email === null || $password === '') {
        fail('Email and password are required', 400);
    }

    $stmt = db()->prepare('SELECT * FROM staff_users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch();
    if (!$row || !verify_password($password, $row['password'] ?? null)) {
        fail('Invalid email or password', 401);
    }

    respond(['data' => staff_payload($row), 'message' => 'Login successful']);
}

/**
 * Create a notification row for a staff user and attempt a best-effort
 * email. $contactId may be null (e.g. generic messages). Returns the id.
 */
function notify_staff(int $staffId, ?int $contactId, string $type, string $title, string $detail): int
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO notifications (staff_id, contact_id, type, title, detail, created_at)
         VALUES (:sid, :cid, :type, :title, :detail, NOW())'
    );
    $stmt->execute([
        ':sid' => $staffId,
        ':cid' => $contactId,
        ':type' => $type,
        ':title' => mb_substr($title, 0, 250),
        ':detail' => mb_substr($detail, 0, 495),
    ]);
    $id = (int)$pdo->lastInsertId();

    // Best-effort email copy of the notification.
    $recipient = $pdo->prepare('SELECT email, first_name, last_name FROM staff_users WHERE id = :id');
    $recipient->execute([':id' => $staffId]);
    $r = $recipient->fetch();
    if ($r && ($r['email'] ?? null) !== null) {
        $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        $body = '<p>Hi ' . htmlspecialchars($name ?: 'there') . ',</p>'
            . '<p>' . htmlspecialchars($title) . '</p>'
            . '<p>' . htmlspecialchars($detail) . '</p>'
            . '<p style="color:#64748b;font-size:12px">Evee CRM notification</p>';
        send_notification_email($r['email'], $title, $body);
    }

    return $id;
}

/** GET /notifications?staff_id=N[&unread=1] -> list for one user. */
function list_notifications(array $filters): void
{
    $staffId = to_int((string)($filters['staff_id'] ?? 0));
    if ($staffId <= 0) fail('staff_id is required');

    $where = 'n.staff_id = :sid';
    $params = [':sid' => $staffId];
    if (!empty($filters['unread'])) {
        $where .= ' AND n.is_read = 0';
    }

    $rows = db()->prepare(
        'SELECT n.id, n.contact_id, n.type, n.title, n.detail, n.is_read, n.created_at,
                c.full_name AS contact_name
           FROM notifications n
           LEFT JOIN contacts c ON c.id = n.contact_id
          WHERE ' . $where . '
          ORDER BY n.created_at DESC, n.id DESC
          LIMIT 100'
    );
    $rows->execute($params);
    respond(['data' => $rows->fetchAll(), 'count' => $rows->rowCount()]);
}

/** GET /notifications/unread-count?staff_id=N */
function notifications_unread_count(int $staffId): void
{
    if ($staffId <= 0) fail('staff_id is required');
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE staff_id = :sid AND is_read = 0');
    $stmt->execute([':sid' => $staffId]);
    respond(['data' => ['unread' => (int)$stmt->fetchColumn()]]);
}

/** POST /notifications/{id}/read */
function mark_notification_read(int $id): void
{
    $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id');
    $stmt->execute([':id' => $id]);
    respond(['message' => 'Notification marked as read']);
}

/** POST /notifications/read-all  { staff_id } */
function mark_all_notifications_read(array $body): void
{
    $staffId = to_int((string)($body['staff_id'] ?? 0));
    if ($staffId <= 0) fail('staff_id is required');
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE staff_id = :sid AND is_read = 0')
        ->execute([':sid' => $staffId]);
    respond(['message' => 'All notifications marked as read']);
}

/**
 * POST /notifications  { staff_ids: [], contact_id?, type?, title, detail }
 * Generic client-side notification (e.g. @mention in an internal comment,
 * or a message sent to a contact whose owner/followers should be notified).
 */
function create_notification(array $body): void
{
    $staffIds = array_values(array_unique(array_map(
        'to_int',
        array_filter((array)($body['staff_ids'] ?? []), fn($s) => (int)$s > 0)
    )));
    if (!$staffIds) fail('staff_ids is required');

    $contactId = isset($body['contact_id']) && (int)$body['contact_id'] > 0
        ? (int)$body['contact_id'] : null;
    $type = normalize_optional($body['type'] ?? null) ?? 'assignment';
    $title = normalize_optional($body['title'] ?? null) ?? 'New notification';
    $detail = normalize_optional($body['detail'] ?? null) ?? '';

    $ids = [];
    foreach ($staffIds as $sid) {
        $ids[] = notify_staff($sid, $contactId, $type, $title, $detail);
    }
    respond(['data' => ['ids' => $ids, 'count' => count($ids)], 'message' => 'Notification(s) created'], 201);
}

/* ----------------------- DEALER / FRANCHISE DASHBOARD ----------------------- */

/**
 * Summary for the owner: per-dealer (User role) lead assignment counts and
 * how many unassigned leads are still waiting to be handed out.
 */
function dealer_dashboard_summary(): void
{
    $rows = db()->query(
        "SELECT s.id AS dealer_id, s.full_name, s.email, s.phone, s.avatar_data,
                COUNT(v.id) AS total,
                SUM(CASE WHEN v.id IS NOT NULL AND COALESCE(dls.status, 'non_contacted') = 'non_contacted' THEN 1 ELSE 0 END) AS non_contacted,
                SUM(CASE WHEN v.id IS NOT NULL AND COALESCE(dls.status, 'non_contacted') = 'contacted' THEN 1 ELSE 0 END) AS contacted,
                SUM(CASE WHEN v.id IS NOT NULL AND COALESCE(dls.status, 'non_contacted') = 'closed' THEN 1 ELSE 0 END) AS closed,
                SUM(CASE WHEN v.id IS NOT NULL AND COALESCE(dls.status, 'non_contacted') = 'customer' THEN 1 ELSE 0 END) AS customer,
                SUM(CASE WHEN v.id IS NOT NULL AND COALESCE(dls.status, 'non_contacted') = 'rejected' THEN 1 ELSE 0 END) AS rejected
           FROM staff_users s
           LEFT JOIN v_leads v ON v.assigned_to = s.id
           LEFT JOIN dealer_lead_status dls ON dls.contact_id = v.id AND dls.dealer_id = s.id
          WHERE s.user_type = 'Dealer'
          GROUP BY s.id, s.full_name, s.email, s.phone, s.avatar_data
          ORDER BY s.full_name"
    )->fetchAll();

    foreach ($rows as &$r) {
        foreach (['total', 'non_contacted', 'contacted', 'closed', 'customer', 'rejected'] as $k) {
            $r[$k] = (int)$r[$k];
        }
    }

    $un = db()->query(
        "SELECT COUNT(*) AS c FROM v_leads v
          WHERE v.assigned_to IS NULL
            AND NOT EXISTS (SELECT 1 FROM dealer_lead_status d WHERE d.contact_id = v.id)"
    )->fetch();
    $unassigned = (int)($un['c'] ?? 0);

    respond(['data' => ['dealers' => $rows, 'unassigned' => $unassigned]]);
}

/**
 * Leads assigned to one dealer (with their tracking status).
 * Includes leads assigned either via the dashboard (dealer_lead_status)
 * or via the CRM contact assignment (contacts.assigned_to). Leads without
 * a tracking row default to non_contacted.
 */
function dealer_leads(int $dealerId, ?string $status = null): void
{
    $sql = "SELECT v.id AS contact_id, v.name, v.phone, v.email, v.business_name, v.created_at,
                   COALESCE(dls.status, 'non_contacted') AS status,
                   COALESCE(dls.response_channel, '') AS response_channel,
                   dls.response_note,
                   dls.contacted_at, dls.responded_at, dls.closed_at, dls.updated_at,
                   v.tags, v.tag_ids
              FROM v_leads v
              LEFT JOIN dealer_lead_status dls
                ON dls.contact_id = v.id AND dls.dealer_id = :dealer
             WHERE v.assigned_to = :dealer2";
    $params = [':dealer' => $dealerId, ':dealer2' => $dealerId];
    if ($status !== null && $status !== '') {
        $sql .= ' AND COALESCE(dls.status, \'non_contacted\') = :status';
        $params[':status'] = $status;
    }
    $sql .= ' ORDER BY COALESCE(dls.updated_at, v.created_at) DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['created_at'] = $row['created_at'] ?? null;
        $row['tags'] = $row['tags'] !== '' ? explode(',', $row['tags']) : [];
        $row['tag_ids'] = $row['tag_ids'] !== '' ? array_map('to_int', explode(',', $row['tag_ids'])) : [];
    }
    respond(['data' => $rows, 'count' => count($rows)]);
}

/** Leads that are not yet assigned to any dealer. */
function dealer_unassigned_leads(): void
{
    $stmt = db()->prepare(
        "SELECT v.* FROM v_leads v
          WHERE v.assigned_to IS NULL
            AND NOT EXISTS (SELECT 1 FROM dealer_lead_status d WHERE d.contact_id = v.id)
          ORDER BY v.created_at DESC"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['tags'] = $row['tags'] !== '' ? explode(',', $row['tags']) : [];
        $row['tag_ids'] = $row['tag_ids'] !== '' ? array_map('to_int', explode(',', $row['tag_ids'])) : [];
        $row['custom_fields'] = decode_custom_fields($row['custom_fields'] ?? null);
    }
    respond(['data' => $rows, 'count' => count($rows)]);
}

/**
 * Assign leads to a dealer.
 * Either explicit ids (checkbox selection) OR a date filter:
 *   { "filter": { "type": "days"|"weeks"|"months"|"years", "value": N } }
 *   { "filter": { "type": "range", "from": "YYYY-MM-DD", "to": "YYYY-MM-DD" } }
 *   { "filter": { "type": "all" } }
 */
function assign_leads_to_dealer(array $body): void
{
    $dealerId = to_int((string)($body['dealer_id'] ?? 0));
    if ($dealerId <= 0) fail('dealer_id is required');

    $d = db()->prepare('SELECT id, user_type FROM staff_users WHERE id = :id');
    $d->execute([':id' => $dealerId]);
    $drow = $d->fetch();
    if (!$drow) fail('Dealer not found', 404);
    if (($drow['user_type'] ?? '') !== 'Dealer') fail('Selected user is not a Dealer', 400);

    $pdo = db();
    $contactIds = [];

    if (!empty($body['contact_ids']) && is_array($body['contact_ids'])) {
        $contactIds = array_values(array_filter(
            array_map(fn($c) => to_int((string)$c), $body['contact_ids']),
            fn($c) => $c > 0
        ));
    } elseif (isset($body['filter']) && is_array($body['filter'])) {
        $f = $body['filter'];
        $type = (string)($f['type'] ?? 'all');
        $where = 'is_lead = 1';
        if (in_array($type, ['days', 'weeks', 'months', 'years'], true)) {
            $value = max(1, (int)($f['value'] ?? 1));
            $where .= " AND created_at >= DATE_SUB(NOW(), INTERVAL $value $type)";
        } elseif ($type === 'range') {
            $from = trim((string)($f['from'] ?? ''));
            $to = trim((string)($f['to'] ?? ''));
            if ($from !== '') {
                $where .= ' AND created_at >= :from';
                $fromParam[':from'] = $from;
            }
            if ($to !== '') {
                $where .= ' AND created_at <= :to';
                $toParam[':to'] = $to;
            }
        }
        // Only leads that aren't already assigned to a dealer.
        $sql = "SELECT id FROM v_leads WHERE $where AND v_leads.assigned_to IS NULL
                  AND NOT EXISTS (
                    SELECT 1 FROM dealer_lead_status d WHERE d.contact_id = v_leads.id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(($fromParam ?? []) + ($toParam ?? []));
        $contactIds = array_map(fn($r) => (int)$r['id'], $stmt->fetchAll());
    }

    if (!$contactIds) {
        respond(['data' => ['assigned' => 0], 'message' => 'No leads to assign']);
    }

    $pdo->beginTransaction();
    try {
        $assigned = 0;
        $ins = $pdo->prepare('INSERT INTO dealer_lead_status (contact_id, dealer_id) VALUES (:c, :d)');
        $upd = $pdo->prepare('UPDATE contacts SET assigned_to = :d, last_activity_at = NOW() WHERE id = :c');
        foreach ($contactIds as $cid) {
            $exists = $pdo->prepare('SELECT id FROM contacts WHERE id = :id');
            $exists->execute([':id' => $cid]);
            if (!$exists->fetch()) continue;

            $taken = $pdo->prepare('SELECT dealer_id FROM dealer_lead_status WHERE contact_id = :c LIMIT 1');
            $taken->execute([':c' => $cid]);
            $cur = $taken->fetchColumn();
            if ($cur !== false) {
                if ((int)$cur === $dealerId) continue;
                $pdo->prepare('DELETE FROM dealer_lead_status WHERE contact_id = :c')->execute([':c' => $cid]);
            }

            $ins->execute([':c' => $cid, ':d' => $dealerId]);
            $upd->execute([':d' => $dealerId, ':c' => $cid]);
            $assigned++;
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        fail('Database error: ' . $e->getMessage(), 500);
    }

    respond(['data' => ['assigned' => $assigned], 'message' => "$assigned lead(s) assigned"]);
}

/** A dealer updates the tracking status of one of their assigned leads. */
function update_dealer_lead_status(int $contactId, array $body): void
{
    $dealerId = to_int((string)($body['dealer_id'] ?? 0));
    if ($dealerId <= 0) fail('dealer_id is required');

    $allowed = ['non_contacted', 'contacted', 'closed', 'customer', 'rejected'];
    $status = (string)($body['status'] ?? '');
    if (!in_array($status, $allowed, true)) fail('Invalid status');

    $channel = (string)($body['response_channel'] ?? '');
    $note = $body['response_note'] ?? null;
    if ($note !== null && trim((string)$note) === '') $note = null;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $chk = $pdo->prepare(
            'SELECT c.id FROM contacts c
              WHERE c.id = :c
                AND (c.assigned_to = :d OR EXISTS (
                       SELECT 1 FROM dealer_lead_status dl WHERE dl.contact_id = c.id AND dl.dealer_id = :d2))'
        );
        $chk->execute([':c' => $contactId, ':d' => $dealerId, ':d2' => $dealerId]);
        if (!$chk->fetch()) fail('Lead not assigned to this dealer', 404);

        $ins = $pdo->prepare(
            'INSERT INTO dealer_lead_status (contact_id, dealer_id, status, response_channel, response_note)
             VALUES (:c, :d, :status, :channel, :note)
             ON DUPLICATE KEY UPDATE status = VALUES(status), response_channel = VALUES(response_channel), response_note = VALUES(response_note)'
        );
        $params = [
            ':c' => $contactId,
            ':d' => $dealerId,
            ':status' => $status,
            ':channel' => $channel,
            ':note' => $note,
        ];
        $ins->execute($params);

        $sets = ['status = :status', 'response_channel = :channel', 'response_note = :note'];
        if (in_array($status, ['contacted', 'closed', 'customer', 'rejected'], true)) {
            $sets[] = 'contacted_at = COALESCE(contacted_at, NOW())';
        }
        if (in_array($status, ['closed', 'customer', 'rejected'], true)) {
            $sets[] = 'responded_at = COALESCE(responded_at, NOW())';
        }
        if (in_array($status, ['customer', 'rejected'], true)) {
            $sets[] = 'closed_at = COALESCE(closed_at, NOW())';
        }

        $pdo->prepare(
            'UPDATE dealer_lead_status SET ' . implode(', ', $sets)
            . ' WHERE contact_id = :c AND dealer_id = :d'
        )->execute($params);
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        fail('Database error: ' . $e->getMessage(), 500);
    }

    respond(['message' => 'Lead status updated']);
}

/** Move many leads into a bucket at once (checkbox selection). */
function bulk_update_dealer_lead_status(array $body): void
{
    $dealerId = to_int((string)($body['dealer_id'] ?? 0));
    if ($dealerId <= 0) fail('dealer_id is required');

    $allowed = ['non_contacted', 'contacted', 'closed', 'customer', 'rejected'];
    $status = (string)($body['status'] ?? '');
    if (!in_array($status, $allowed, true)) fail('Invalid status');

    $channel = (string)($body['response_channel'] ?? '');
    $note = $body['response_note'] ?? null;
    if ($note !== null && trim((string)$note) === '') $note = null;

    $ids = $body['contact_ids'] ?? null;
    if (!is_array($ids) || !$ids) fail('contact_ids are required');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $updated = 0;
        $ins = $pdo->prepare(
            'INSERT INTO dealer_lead_status (contact_id, dealer_id, status, response_channel, response_note)
             VALUES (:c, :d, :status, :channel, :note)
             ON DUPLICATE KEY UPDATE status = VALUES(status), response_channel = VALUES(response_channel), response_note = VALUES(response_note)'
        );
        $upd = $pdo->prepare(
            'UPDATE dealer_lead_status SET status = :status, response_channel = :channel, response_note = :note,
                    contacted_at = CASE WHEN :contacted THEN COALESCE(contacted_at, NOW()) ELSE contacted_at END,
                    responded_at = CASE WHEN :responded THEN COALESCE(responded_at, NOW()) ELSE responded_at END,
                    closed_at = CASE WHEN :closed THEN COALESCE(closed_at, NOW()) ELSE closed_at END
             WHERE contact_id = :c AND dealer_id = :d'
        );
        foreach ($ids as $raw) {
            $cid = to_int((string)$raw);
            if ($cid <= 0) continue;
            $chk = $pdo->prepare(
                'SELECT c.id FROM contacts c
                  WHERE c.id = :c
                    AND (c.assigned_to = :d OR EXISTS (
                           SELECT 1 FROM dealer_lead_status dl WHERE dl.contact_id = c.id AND dl.dealer_id = :d2))'
            );
            $chk->execute([':c' => $cid, ':d' => $dealerId, ':d2' => $dealerId]);
            if (!$chk->fetch()) continue;

            $ins->execute([
                ':c' => $cid,
                ':d' => $dealerId,
                ':status' => $status,
                ':channel' => $channel,
                ':note' => $note,
            ]);
            $upd->execute([
                ':status' => $status,
                ':channel' => $channel,
                ':note' => $note,
                ':contacted' => in_array($status, ['contacted', 'closed', 'customer', 'rejected'], true) ? 1 : 0,
                ':responded' => in_array($status, ['closed', 'customer', 'rejected'], true) ? 1 : 0,
                ':closed' => in_array($status, ['customer', 'rejected'], true) ? 1 : 0,
                ':c' => $cid,
                ':d' => $dealerId,
            ]);
            $updated++;
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        fail('Database error: ' . $e->getMessage(), 500);
    }

    respond(['data' => ['updated' => $updated], 'message' => "$updated lead(s) moved to $status"]);
}

/* ------------------- SMART LISTS (server-side, multi-user) ------------------- */

/**
 * Resolve the current acting user id. Endpoints accept an explicit
 * "user_id" (the logged-in staff member) since the CRM has no session.
 */
function smart_list_user(array $filters, array $body = []): int
{
    $id = to_int((string)($body['user_id'] ?? ($filters['user_id'] ?? 0)));
    if ($id <= 0) fail('user_id is required');
    return $id;
}

/** Build the array representation of a smart list row + its shares. */
function smart_list_payload(array $row): array
{
    $shares = db()->prepare(
        'SELECT user_id FROM smart_list_shares WHERE smart_list_id = :id ORDER BY user_id'
    );
    $shares->execute([':id' => $row['id']]);
    $shareIds = array_map(fn($s) => (int)$s['user_id'], $shares->fetchAll());

    return [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'filters' => decode_json_field($row['filters'] ?? null),
        'sort_by' => $row['sort_by'] ?? '',
        'fields' => decode_json_field($row['fields'] ?? null),
        'members' => decode_json_field($row['members'] ?? null),
        'dealer_id' => $row['dealer_id'] !== null ? (int)$row['dealer_id'] : null,
        'dealer_name' => $row['dealer_name'] ?? null,
        'shared_all' => (int)($row['shared_all'] ?? 0) === 1,
        'shared_user_ids' => $shareIds,
        'created_by' => (int)$row['created_by'],
        'created_by_name' => $row['created_by_name'] ?? '',
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

/** Visibility: a list is visible when owned, shared explicitly, or shared with everyone. */
function smart_lists_visible(int $userId): PDOStatement
{
    return db()->prepare(
        'SELECT sl.*, s.full_name AS created_by_name, d.full_name AS dealer_name
           FROM smart_lists sl
           LEFT JOIN staff_users s ON s.id = sl.created_by
           LEFT JOIN staff_users d ON d.id = sl.dealer_id
          WHERE sl.created_by = :me
             OR sl.shared_all = 1
             OR sl.id IN (SELECT smart_list_id FROM smart_list_shares WHERE user_id = :me2)
          ORDER BY sl.created_at DESC, sl.id DESC'
    );
}

/** GET /smart-lists?user_id=N -> every list visible to the user. */
function list_smart_lists(array $filters): void
{
    $userId = smart_list_user($filters);
    $stmt = smart_lists_visible($userId);
    $stmt->execute([':me' => $userId, ':me2' => $userId]);
    $rows = $stmt->fetchAll();

    respond(['data' => array_map('smart_list_payload', $rows), 'count' => count($rows)]);
}

/** Validate and normalize the JSON-column inputs of a smart list. */
function smart_list_input(array $body): array
{
    $name = trim((string)($body['name'] ?? ''));
    if ($name === '') fail('name is required');

    $asIntArray = function ($value): array {
        if (!is_array($value)) return [];
        return array_values(array_unique(array_filter(
            array_map(fn($v) => to_int((string)$v), $value),
            fn($v) => $v > 0
        )));
    };
    $asStrArray = function ($value): array {
        if (!is_array($value)) return [];
        return array_values(array_filter(array_map(fn($v) => trim((string)$v), $value), fn($v) => $v !== ''));
    };

    $dealerId = $body['dealer_id'] ?? null;

    return [
        'name' => $name,
        'filters' => encode_json_field($asStrArray($body['filters'] ?? null)),
        'sort_by' => (($body['sort_by'] ?? '') !== '') ? trim((string)$body['sort_by']) : null,
        'fields' => encode_json_field($asStrArray($body['fields'] ?? null)),
        'members' => encode_json_field($asIntArray($body['members'] ?? null)),
        'dealer_id' => ($dealerId === null || $dealerId === '' || (int)$dealerId <= 0) ? null : (int)$dealerId,
        'shared_all' => !empty($body['shared_all']) ? 1 : 0,
        'share_ids' => $asIntArray($body['shared_user_ids'] ?? null),
    ];
}

/** Replace the share rows for a smart list (owner always keeps access). */
function smart_list_set_shares(int $listId, array $shareIds, int $ownerId): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM smart_list_shares WHERE smart_list_id = :id')->execute([':id' => $listId]);
    $ins = $pdo->prepare('INSERT INTO smart_list_shares (smart_list_id, user_id) VALUES (:l, :u)');
    foreach ($shareIds as $uid) {
        if ((int)$uid === $ownerId) continue;
        $ins->execute([':l' => $listId, ':u' => (int)$uid]);
    }
}

/** Fetch a smart list row with creator/dealer names. */
function smart_list_fetch(int $id): array
{
    $row = db()->prepare(
        'SELECT sl.*, s.full_name AS created_by_name, d.full_name AS dealer_name
           FROM smart_lists sl
           LEFT JOIN staff_users s ON s.id = sl.created_by
           LEFT JOIN staff_users d ON d.id = sl.dealer_id
          WHERE sl.id = :id'
    );
    $row->execute([':id' => $id]);
    return $row->fetch() ?: [];
}

/** POST /smart-lists  { user_id, name, filters?, sort_by?, fields?, members?, dealer_id?, shared_all?, shared_user_ids? } */
function create_smart_list(array $body): void
{
    $userId = smart_list_user([], $body);
    $input = smart_list_input($body);
    $pdo = db();

    $dup = $pdo->prepare('SELECT id FROM smart_lists WHERE created_by = :me AND name = :name');
    $dup->execute([':me' => $userId, ':name' => $input['name']]);
    if ($dup->fetch()) fail('A smart list with this name already exists');

    $stmt = $pdo->prepare(
        'INSERT INTO smart_lists (name, filters, sort_by, fields, members, dealer_id, shared_all, created_by)
         VALUES (:name, :filters, :sort_by, :fields, :members, :dealer_id, :shared_all, :created_by)'
    );
    $stmt->execute([
        ':name' => $input['name'],
        ':filters' => $input['filters'],
        ':sort_by' => $input['sort_by'],
        ':fields' => $input['fields'],
        ':members' => $input['members'],
        ':dealer_id' => $input['dealer_id'],
        ':shared_all' => $input['shared_all'],
        ':created_by' => $userId,
    ]);
    $id = (int)$pdo->lastInsertId();
    smart_list_set_shares($id, $input['share_ids'], $userId);

    respond(['data' => smart_list_payload(smart_list_fetch($id)), 'message' => 'Smart list created'], 201);
}

/** PUT /smart-lists/{id}  { user_id, name, ... } -> only the owner may edit. */
function update_smart_list(int $id, array $body): void
{
    $userId = smart_list_user([], $body);
    $input = smart_list_input($body);
    $pdo = db();

    $existing = $pdo->prepare('SELECT id, created_by FROM smart_lists WHERE id = :id');
    $existing->execute([':id' => $id]);
    $row = $existing->fetch();
    if (!$row) fail('Smart list not found', 404);
    if ((int)$row['created_by'] !== $userId) fail('Only the owner can edit this smart list', 403);

    $dup = $pdo->prepare('SELECT id FROM smart_lists WHERE created_by = :me AND name = :name AND id <> :id');
    $dup->execute([':me' => $userId, ':name' => $input['name'], ':id' => $id]);
    if ($dup->fetch()) fail('A smart list with this name already exists');

    $pdo->prepare(
        'UPDATE smart_lists
            SET name = :name, filters = :filters, sort_by = :sort_by, fields = :fields,
                members = :members, dealer_id = :dealer_id, shared_all = :shared_all
          WHERE id = :id'
    )->execute([
        ':name' => $input['name'],
        ':filters' => $input['filters'],
        ':sort_by' => $input['sort_by'],
        ':fields' => $input['fields'],
        ':members' => $input['members'],
        ':dealer_id' => $input['dealer_id'],
        ':shared_all' => $input['shared_all'],
        ':id' => $id,
    ]);
    smart_list_set_shares($id, $input['share_ids'], $userId);

    respond(['data' => smart_list_payload(smart_list_fetch($id)), 'message' => 'Smart list updated']);
}

/** DELETE /smart-lists/{id}?user_id=N -> only the owner may delete. */
function delete_smart_list(int $id, array $filters): void
{
    $userId = smart_list_user($filters);
    $pdo = db();

    $existing = $pdo->prepare('SELECT id, created_by FROM smart_lists WHERE id = :id');
    $existing->execute([':id' => $id]);
    $row = $existing->fetch();
    if (!$row) fail('Smart list not found', 404);
    if ((int)$row['created_by'] !== $userId) fail('Only the owner can delete this smart list', 403);

    $pdo->prepare('DELETE FROM smart_lists WHERE id = :id')->execute([':id' => $id]);
    respond(['message' => 'Smart list deleted']);
}

/** POST /smart-lists/{id}/duplicate  { user_id } -> a copy owned by the caller. */
function duplicate_smart_list(int $id, array $body): void
{
    $userId = smart_list_user([], $body);
    $pdo = db();

    $src = $pdo->prepare('SELECT * FROM smart_lists WHERE id = :id');
    $src->execute([':id' => $id]);
    $row = $src->fetch();
    if (!$row) fail('Smart list not found', 404);

    $name = $row['name'] . ' (copy)';
    $dup = $pdo->prepare('SELECT id FROM smart_lists WHERE created_by = :me AND name = :name');
    $dup->execute([':me' => $userId, ':name' => $name]);
    if ($dup->fetch()) fail('A smart list named "' . $name . '" already exists');

    $pdo->prepare(
        'INSERT INTO smart_lists (name, filters, sort_by, fields, members, dealer_id, shared_all, created_by)
         VALUES (:name, :filters, :sort_by, :fields, :members, :dealer_id, 0, :created_by)'
    )->execute([
        ':name' => $name,
        ':filters' => $row['filters'],
        ':sort_by' => $row['sort_by'],
        ':fields' => $row['fields'],
        ':members' => $row['members'],
        ':dealer_id' => $row['dealer_id'],
        ':created_by' => $userId,
    ]);
    $newId = (int)$pdo->lastInsertId();

    respond(['data' => smart_list_payload(smart_list_fetch($newId)), 'message' => 'Smart list duplicated']);
}

/* ----------------------- ROUTER ----------------------- */

$method = $_SERVER['REQUEST_METHOD'];
$parts = route_parts();
$filters = $_GET;

$resource = $parts[0] ?? 'contacts';

switch ($resource) {
    case 'contacts':
        if ($method === 'GET') {
            $id = $parts[1] ?? null;
            $sub = $parts[2] ?? null;
            if ($id && $sub) {
                switch ($sub) {
                    case 'opportunities': list_opportunities(to_int($id)); break;
                    case 'tasks':         list_tasks(to_int($id));         break;
                    case 'notes':         list_notes(to_int($id));         break;
                    case 'appointments':  list_appointments(to_int($id));  break;
                    case 'followers':     list_followers(to_int($id));     break;
                    case 'activities':    list_activities(to_int($id));    break;
                    default: fail('Unknown sub-resource', 404);
                }
            }
            $id ? get_contact(to_int($id)) : list_contacts($filters);
        } elseif ($method === 'POST') {
            $body = json_body();
            if (($parts[1] ?? null) === 'bulk-delete') bulk_delete($body);
            elseif (($parts[2] ?? null) !== null) {
                $cid = to_int($parts[1]);
                switch ($parts[2]) {
                    case 'opportunities': create_opportunity($cid, $body); break;
                    case 'tasks':         create_task($cid, $body);        break;
                    case 'notes':         create_note($cid, $body);        break;
                    case 'appointments':  create_appointment($cid, $body); break;
                    case 'followers':     add_follower($cid, $body);       break;
                    case 'activities':    create_activity($cid, $body);    break;
                    default: fail('Unknown sub-resource', 404);
                }
            } else {
                create_contact($body);
            }
        } elseif ($method === 'PUT') {
            $id = $parts[1] ?? null;
            if (!$id) fail('Contact id required');
            update_contact(to_int($id), json_body());
        } elseif ($method === 'DELETE') {
            $id = $parts[1] ?? null;
            $sub = $parts[2] ?? null;
            if (!$id) fail('Contact id required');
            if ($sub === 'followers') {
                $staffId = to_int($parts[3] ?? 0);
                if ($staffId <= 0) fail('staff_id required');
                remove_follower(to_int($id), $staffId);
            } else {
                delete_contact(to_int($id));
            }
        }
        break;

    case 'leads':
        if ($method === 'GET') list_leads($filters);
        break;

    case 'tags':
        if ($method === 'GET') list_tags();
        break;

    case 'campaigns':
        if ($method === 'GET') list_campaigns($filters);
        break;

    case 'workflows':
        if ($method === 'GET') list_workflows($filters);
        break;

    case 'staff':
        $id = $parts[1] ?? null;
        if ($method === 'GET') {
            if ($id) {
                $rows = db()->query('SELECT * FROM staff_users WHERE id = ' . (int)$id)->fetchAll();
                if (!$rows) fail('Staff user not found', 404);
                respond(['data' => staff_payload($rows[0])]);
            }
            list_staff();
        } elseif ($method === 'POST') {
            create_staff(json_body());
        } elseif ($method === 'PUT') {
            if (!$id) fail('Staff id required');
            update_staff(to_int($id), json_body());
        } elseif ($method === 'DELETE') {
            if (!$id) fail('Staff id required');
            delete_staff(to_int($id));
        }
        break;

    case 'opportunities':
        if ($method === 'DELETE') {
            $id = $parts[1] ?? null;
            if (!$id) fail('Opportunity id required');
            delete_opportunity(to_int($id));
        }
        break;

    case 'tasks':
        if ($method === 'DELETE') {
            $id = $parts[1] ?? null;
            if (!$id) fail('Task id required');
            delete_task(to_int($id));
        }
        break;

    case 'notes':
        if ($method === 'DELETE') {
            $id = $parts[1] ?? null;
            if (!$id) fail('Note id required');
            delete_note(to_int($id));
        }
        break;

    case 'appointments':
        if ($method === 'DELETE') {
            $id = $parts[1] ?? null;
            if (!$id) fail('Appointment id required');
            delete_appointment(to_int($id));
        }
        break;

    case 'auth':
        if ($method === 'POST') {
            login(json_body());
        }
        break;

    case 'dealer-dashboard':
        $sub = $parts[1] ?? null;
        if ($method === 'GET') {
            if ($sub === 'summary') {
                dealer_dashboard_summary();
            } elseif ($sub === 'unassigned') {
                dealer_unassigned_leads();
            } elseif ($sub === 'leads') {
                $dealerId = to_int((string)($filters['dealer_id'] ?? 0));
                if ($dealerId <= 0) fail('dealer_id is required');
                dealer_leads($dealerId, isset($filters['status']) ? (string)$filters['status'] : null);
            } else {
                fail('Unknown sub-resource', 404);
            }
        } elseif ($method === 'POST') {
            if ($sub === 'assign') {
                assign_leads_to_dealer(json_body());
            } elseif ($sub === 'bulk-status') {
                bulk_update_dealer_lead_status(json_body());
            } else {
                fail('Unknown sub-resource', 404);
            }
        } elseif ($method === 'PUT') {
            if ($sub === 'leads' && ($parts[3] ?? null) === 'status') {
                update_dealer_lead_status(to_int($parts[2]), json_body());
            } else {
                fail('Unknown sub-resource', 404);
            }
        }
        break;

    case 'smart-lists':
        $id = $parts[1] ?? null;
        $sub = $parts[2] ?? null;
        if ($method === 'GET') {
            list_smart_lists($filters);
        } elseif ($method === 'POST') {
            if ($id && $sub === 'duplicate') {
                duplicate_smart_list(to_int($id), json_body());
            } else {
                create_smart_list(json_body());
            }
        } elseif ($method === 'PUT') {
            if (!$id) fail('Smart list id required');
            update_smart_list(to_int($id), json_body());
        } elseif ($method === 'DELETE') {
            if (!$id) fail('Smart list id required');
            delete_smart_list(to_int($id), $filters);
        }
        break;

    case 'notifications':
        if ($method === 'GET') {
            if (($parts[1] ?? null) === 'unread-count') {
                notifications_unread_count(to_int((string)($filters['staff_id'] ?? 0)));
            }
            list_notifications($filters);
        } elseif ($method === 'POST') {
            if (($parts[1] ?? null) === 'read-all') {
                mark_all_notifications_read(json_body());
            } elseif (isset($parts[1]) && ($parts[2] ?? null) === 'read') {
                mark_notification_read(to_int($parts[1]));
            } else {
                create_notification(json_body());
            }
        }
        break;

    default:
        fail('Unknown endpoint', 404);
}
