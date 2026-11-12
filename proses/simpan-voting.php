<?php
declare(strict_types=1);

/**
 * Endpoint Pemrosesan Suara Pemilih (POST + CSRF + PRG)
 * Tidak mengizinkan voting via GET dan memvalidasi seluruh aturan di server.
 */

require_once dirname(__DIR__) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/app/helpers/ResponseHelper.php';
require_once dirname(__DIR__) . '/app/helpers/AuthHelper.php';
require_once dirname(__DIR__) . '/app/helpers/ValidationHelper.php';
require_once dirname(__DIR__) . '/app/models/User.php';
require_once dirname(__DIR__) . '/app/controllers/AuthController.php';
require_once dirname(__DIR__) . '/app/middleware/AuthMiddleware.php';
require_once dirname(__DIR__) . '/app/controllers/VotingController.php';

$acceptHeader = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
$isAjax = (
    strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
    || str_contains($acceptHeader, 'application/json')
);

if ($isAjax) {
    startSecureSession();
    if (!isLoggedIn()) {
        (new AuthController($pdo))->attemptRememberLogin();
    }

    if (!isLoggedIn()) {
        jsonResponse([
            'success' => false,
            'code'    => 'UNAUTHENTICATED',
            'message' => 'Silakan masuk ke akun Anda terlebih dahulu untuk melakukan voting.',
        ], 401);
    }

    $sessionUser = currentUser();
    $freshUser = $sessionUser !== null ? (new User($pdo))->findById((int) $sessionUser['id']) : null;
    if ($freshUser === null) {
        if ($sessionUser !== null) {
            (new User($pdo))->deleteRememberTokens((int) $sessionUser['id'], null);
        }
        clearRememberCookie();
        logoutSession();
        jsonResponse([
            'success' => false,
            'code'    => 'SESSION_INVALID',
            'message' => 'Sesi akun tidak valid. Silakan login kembali.',
        ], 401);
    }

    $user = [
        'id'    => (int) $freshUser['id'],
        'nama'  => (string) $freshUser['nama'],
        'email' => (string) $freshUser['email'],
        'role'  => (string) $freshUser['role'],
    ];
} else {
    $user = AuthMiddleware::handle($pdo);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        jsonResponse([
            'success' => false,
            'code'    => 'METHOD_NOT_ALLOWED',
            'message' => 'Pengiriman suara melalui GET tidak diperbolehkan. Gunakan metode POST.',
        ], 405);
    }

    setFlash(
        'warning',
        'Metode Tidak Diizinkan',
        'Pengiriman suara melalui GET tidak diperbolehkan. Silakan kirim suara melalui formulir konfirmasi resmi.'
    );
    redirect('pages/voting.php');
}

$votingController = new VotingController($pdo);
$result = $votingController->submitVote($user, $_POST);

if ($isAjax) {
    $statusCode = 422;
    if ($result['success']) {
        $statusCode = 200;
    } elseif (in_array($result['code'], ['INVALID_CSRF', 'ROLE_FORBIDDEN'], true)) {
        $statusCode = 403;
    }

    jsonResponse([
        'success'      => $result['success'],
        'code'         => $result['code'],
        'message'      => $result['message'],
        'redirect_url' => baseUrl($result['redirect_path']),
    ], $statusCode);
}

if ($result['success']) {
    setFlash('success', 'Suara Berhasil Disimpan', $result['message']);
} else {
    setFlash('error', 'Voting Ditolak', $result['message']);
}

redirect($result['redirect_path']);

