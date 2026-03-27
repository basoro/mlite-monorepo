<?php
/**
 * UI Sederhana Implementasi API mLITE (Modul Pasien)
 * Terdiri dari fitur List dan Tambah Pasien menggunakan Web HTML & Bootstrap
 */

class MLiteApiClient {
    private $baseUrl;
    private $apiKey;
    private $usernamePerm;
    private $passwordPerm;
    private $token = '';

    public function __construct($baseUrl, $apiKey, $usernamePerm, $passwordPerm) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->usernamePerm = $usernamePerm;
        $this->passwordPerm = $passwordPerm;
    }

    public function login($username, $password) {
        $url = $this->baseUrl . '/admin/api/login';
        
        $headers = [
            'Content-Type: application/json',
            'X-Api-Key: ' . $this->apiKey
        ];
        
        $body = json_encode(['username' => $username, 'password' => $password]);
        
        $response = $this->request('POST', $url, $headers, $body);
        $data = json_decode($response, true);
        
        if (isset($data['token'])) {
            $this->token = $data['token'];
            return true;
        }
        return false;
    }

    private function getDefaultHeaders() {
        return [
            'X-Api-Key: ' . $this->apiKey,
            'Authorization: Bearer ' . $this->token,
            'X-Username-Permission: ' . $this->usernamePerm,
            'X-Password-Permission: ' . $this->passwordPerm,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
    }

    // [GET] List
    public function getPasienList($page = 1, $perPage = 10, $search = '') {
        $url = $this->baseUrl . '/admin/api/pasien/list?page=' . $page . '&per_page=' . $perPage . '&s=' . urlencode($search);
        return $this->request('GET', $url, $this->getDefaultHeaders());
    }

    // [POST] Create
    public function createPasien($data) {
        $url = $this->baseUrl . '/admin/api/pasien/create';
        return $this->request('POST', $url, $this->getDefaultHeaders(), json_encode($data));
    }

    // [DELETE] Delete
    public function deletePasien($noRkmMedis) {
        $url = $this->baseUrl . '/admin/api/pasien/delete/' . urlencode($noRkmMedis);
        return $this->request('DELETE', $url, $this->getDefaultHeaders());
    }

    private function request($method, $url, $headers, $body = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        return $error ? json_encode(['status' => 'error', 'message' => $error]) : $response;
    }
}

// ==========================================
// KONFIGURASI KREDENSIAL API (UBAH DISINI)
// ==========================================
$baseUrl = 'https://demo.mlite.id'; // Base URL mLITE (ganti sesuai server Anda)
$apiKey = 'YOUR_API_KEY_HERE'; // API Key di modul mLITE API Key
$usernamePerm = 'admin'; // Username Permission di modul mLITE API Key
$passwordPerm = 'admin'; // Password Permission di modul mLITE API Key
$adminUser = 'DR001'; // Username Login mLITE
$adminPass = '12345678'; // Password Login mLITE

$client = new MLiteApiClient($baseUrl, $apiKey, $usernamePerm, $passwordPerm);
$isLoggedIn = $client->login($adminUser, $adminPass);

$message = '';
$pasienData = [];

// Proses form Tambah / Hapus
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        $newData = [
            "no_rkm_medis" => $_POST['no_rkm_medis'],
            "nm_pasien"    => $_POST['nm_pasien'],
            "no_ktp"       => $_POST['no_ktp'],
            "jk"           => $_POST['jk'],
            "tgl_lahir"    => $_POST['tgl_lahir'],
            "alamat"       => $_POST['alamat'],
            "no_tlp"       => $_POST['no_tlp'],
            "kd_pj"        => $_POST['kd_pj']
        ];
        
        $resCreate = json_decode($client->createPasien($newData), true);
        
        // Asumsi response sukses mLite bisa menggunakan "status" atau "success"
        if ((isset($resCreate['success']) && $resCreate['success']) || (isset($resCreate['status']) && $resCreate['status'] === 'success')) {
            $message = '<div class="alert alert-success">Pasien berhasil ditambahkan.</div>';
        } else {
            $message = '<div class="alert alert-warning">Response API: ' . json_encode($resCreate) . '</div>';
        }
    } 
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $noRM = $_POST['no_rkm_medis'];
        $resDelete = json_decode($client->deletePasien($noRM), true);
        
        if ((isset($resDelete['success']) && $resDelete['success']) || (isset($resDelete['status']) && $resDelete['status'] === 'success')) {
            $message = '<div class="alert alert-success">Pasien berhasil dihapus.</div>';
        } else {
            $message = '<div class="alert alert-warning">Response API Hapus: ' . json_encode($resDelete) . '</div>';
        }
    }
}

// Ambil list pasien (Page 1, 50 Items)
if ($isLoggedIn) {
    $listResponse = json_decode($client->getPasienList(1, 50, ""), true);
    // Menyesuaikan dengan bentuk response pagination mLite (biasanya index "data" yang berisi array object)
    if (isset($listResponse['data'])) {
        $pasienData = $listResponse['data']; 
    } elseif (is_array($listResponse)) { // Terkadang return langsung array
        $pasienData = $listResponse;
    }
} else {
    $message = '<div class="alert alert-danger">Login API Gagal! Harap periksa Konfigurasi Kredensial URL, API Key, dan User/Password Anda di dalam script.</div>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRUD Pasien mLITE</title>
    <!-- CSS Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; }
        .table-responsive { max-height: 500px; overflow-y: auto; }
    </style>
</head>
<body>
<div class="container-fluid mt-4 px-4">
    <h3 class="mb-4 text-primary">🏥 Data Pasien - mLITE API Client</h3>
    
    <?= $message ?>

    <div class="row">
        <?php if ($isLoggedIn): ?>
        <!-- FORM CREATE -->
        <div class="col-md-3">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white fw-bold">
                    Tambah Pasien Baru
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-2">
                            <label class="form-label small fw-bold">No. Rekam Medis</label>
                            <input type="text" name="no_rkm_medis" class="form-control form-control-sm" required placeholder="Ex: 000001">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Nama Pasien</label>
                            <input type="text" name="nm_pasien" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">No. KTP</label>
                            <input type="text" name="no_ktp" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Jenis Kelamin</label>
                            <select name="jk" class="form-select form-select-sm" required>
                                <option value="L">Laki-Laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Tgl. Lahir</label>
                            <input type="date" name="tgl_lahir" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Alamat</label>
                            <textarea name="alamat" class="form-control form-control-sm" rows="2" required></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">No. Telepon</label>
                            <input type="text" name="no_tlp" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Kode Asuransi (Ex: UMU : Umum)</label>
                            <input type="text" name="kd_pj" class="form-control form-control-sm" required value="UMU">
                        </div>
                        <button type="submit" class="btn btn-primary w-100 btn-sm">Simpan Data</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- LIST DATA PASIEN -->
        <div class="col-md-9">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white fw-bold d-flex justify-content-between align-items-center">
                    <span>Daftar Pasien Terbaru</span>
                    <span class="badge bg-light text-dark">Total: <?= count($pasienData) ?></span>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.9rem;">
                        <thead class="table-light text-nowrap" style="position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th>No RM</th>
                                <th>Nama Pasien</th>
                                <th>KTP</th>
                                <th>J.Kel</th>
                                <th>Tgl Lahir</th>
                                <th>Alamat</th>
                                <th>No Telepon</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($pasienData)): ?>
                                <?php foreach($pasienData as $row): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($row['no_rkm_medis'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['nm_pasien'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['no_ktp'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['jk'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['tgl_lahir'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['alamat'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['no_tlp'] ?? '') ?></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data pasien bernama <?= htmlspecialchars($row['nm_pasien'] ?? '') ?>?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="no_rkm_medis" value="<?= htmlspecialchars($row['no_rkm_medis'] ?? '') ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">🗑️</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        Data tidak ditemukan atau API Array kosong.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<!-- JS Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
