<?php
require_once __DIR__ . '/includes/general/verifications.php';
require_once __DIR__ . '/includes/general/conversation.php';

if (strtolower((string) ($_SESSION['email'] ?? '')) !== CONVERSATION_ADMIN_EMAIL) {
    header('Location: administration.php');
    exit;
}

$conversationViewerEmail = CONVERSATION_ADMIN_EMAIL;
$conversationEndpoint = 'conversation-admin.php';
require __DIR__ . '/coucou.php';
