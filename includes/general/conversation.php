<?php
const CONVERSATION_USER_EMAIL = 'clemence.eche@gmail.com';
const CONVERSATION_ADMIN_EMAIL = 'admin@admin.fr';
const CONVERSATION_FILE = __DIR__ . '/../../data/conversation_messages.json';

function conversation_messages(): array
{
    if (!is_file(CONVERSATION_FILE)) {
        return conversation_initial_messages();
    }

    $messages = json_decode(file_get_contents(CONVERSATION_FILE) ?: '[]', true);
    return is_array($messages) ? $messages : [];
}

function conversation_initial_messages(): array
{
    return [
        ['id' => 'intro-1', 'sender' => CONVERSATION_ADMIN_EMAIL, 'message' => 'Coucou 😉', 'created_at' => '2026-08-26T00:00:00+00:00'],
        ['id' => 'intro-2', 'sender' => CONVERSATION_ADMIN_EMAIL, 'message' => "tu ne t'y attendais pas hein 😄", 'created_at' => '2026-08-26T00:01:00+00:00'],
        ['id' => 'intro-3', 'sender' => CONVERSATION_ADMIN_EMAIL, 'message' => "Chaque jour avec toi est un petit cadeau que je n'oublie jamais de déballer. 💕", 'created_at' => '2026-08-26T00:02:00+00:00'],
    ];
}

function conversation_has_messages_from(string $email): bool
{
    foreach (conversation_messages() as $message) {
        if (strtolower((string) ($message['sender'] ?? '')) === strtolower($email)) {
            return true;
        }
    }

    return false;
}

function conversation_add_message(string $email, string $message): array
{
    $message = trim($message);
    if ($message === '' || mb_strlen($message) > 2000) {
        throw new InvalidArgumentException('Message invalide.');
    }

    $handle = fopen(CONVERSATION_FILE, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Stockage indisponible.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Stockage indisponible.');
        }

        $contents = stream_get_contents($handle);
        $messages = json_decode($contents ?: '[]', true);
        if (!is_array($messages)) {
            $messages = conversation_initial_messages();
        }

        $newMessage = [
            'id' => bin2hex(random_bytes(8)),
            'sender' => $email,
            'message' => $message,
            'created_at' => date(DATE_ATOM),
        ];
        $messages[] = $newMessage;

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        fflush($handle);
        flock($handle, LOCK_UN);
        return $newMessage;
    } finally {
        fclose($handle);
    }
}

function conversation_is_allowed(string $email): bool
{
    return hash_equals(strtolower($email), strtolower(CONVERSATION_USER_EMAIL))
        || hash_equals(strtolower($email), strtolower(CONVERSATION_ADMIN_EMAIL));
}
