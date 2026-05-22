<?php
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/feedback_submission.php';

$user = optionalApiUser($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiJson(['success' => false, 'message' => 'POST required.'], 405);
}

$category = (string) ($_POST['category'] ?? 'bug');
$pageWorkflow = trim((string) ($_POST['page_workflow'] ?? ''));
$contactEmail = trim((string) ($_POST['contact_email'] ?? ''));
$details = trim((string) ($_POST['details'] ?? ''));
$sourceVersion = trim((string) ($_POST['source_version'] ?? ''));
$sourceDevice = trim((string) ($_POST['source_device'] ?? ''));

if ($details === '') {
    apiJson(['success' => false, 'message' => 'Please add details before saving.'], 400);
}

$feedbackId = gpSaveFeedbackSubmission($pdo, $user !== null ? (int) $user['id'] : null, [
    'category' => $category,
    'page_workflow' => $pageWorkflow,
    'contact_email' => $contactEmail,
    'details' => $details,
    'source_platform' => 'android',
    'source_label' => 'GuidePaw Companion',
    'source_version' => $sourceVersion,
    'source_device' => $sourceDevice,
]);

$uploadDebug = [];
gpStoreFeedbackAttachments($pdo, $feedbackId, $_FILES['attachments'] ?? [], __DIR__ . '/../uploads/feedback', $uploadDebug);

apiJson([
    'success' => true,
    'message' => $uploadDebug ? 'Report saved, but one or more attachments were skipped.' : 'Report saved successfully.',
    'feedback_id' => $feedbackId,
    'upload_debug' => $uploadDebug,
]);
