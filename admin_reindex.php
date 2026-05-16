<?php
session_start();
require_once __DIR__ . '/seb_check.php';
require_once __DIR__ . '/db_config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/session_timeout.php';

$activePage = 'reindex';

$pending_count = 0;
try {
    $countStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'inactive'");
    $pending_count = $countStmt->fetchColumn();
} catch (PDOException $e) {
    // Ignore
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Index Manager - PGIM Digital Library</title>
    <link rel="icon" type="image/x-icon" href="./assets/img/logo without bg.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <script src="assets/js/theme.js?v=<?php echo time(); ?>"></script>
    <style>
        .admin-card { background: var(--bg-card); padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 2rem; overflow-x: auto; }
        .sidebar-nav li { padding: 0.875rem 1.5rem; display: flex; align-items: center; gap: 1rem; cursor: pointer; transition: all 0.2s; color: var(--text-muted); font-weight: 500;}
        .sidebar-nav li:hover { background-color: var(--bg-hover); color: var(--primary); }
        .sidebar-nav li.active { background-color: var(--bg-active); color: var(--primary); border-right: 3px solid var(--primary); }

        .index-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        .index-header h2 { color: var(--text-main); font-size: 1.25rem; margin: 0; }

        .btn-reindex {
            padding: 0.7rem 1.5rem; border-radius: 8px; border: none; cursor: pointer;
            background: linear-gradient(135deg, #2563eb, #3b82f6); color: white;
            font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;
            transition: all 0.3s; box-shadow: 0 4px 12px rgba(37,99,235,0.3);
        }
        .btn-reindex:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(37,99,235,0.4); }
        .btn-reindex:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .btn-reindex i.fa-spin { animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .stats-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .stat-pill {
            background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px;
            padding: 0.75rem 1.25rem; display: flex; align-items: center; gap: 0.5rem;
            font-size: 0.85rem; color: var(--text-muted);
        }
        .stat-pill strong { color: var(--primary); font-size: 1.1rem; }

        .status-toast {
            display: none; padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;
            font-weight: 500; font-size: 0.9rem; align-items: center; gap: 0.75rem;
        }
        .status-toast.success { display: flex; background: rgba(34,197,94,0.1); color: #16a34a; border: 1px solid rgba(34,197,94,0.3); }
        .status-toast.error { display: flex; background: rgba(239,68,68,0.1); color: #dc2626; border: 1px solid rgba(239,68,68,0.3); }

        /* Modal Styles */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center;
        }
        .modal-content {
            background: var(--bg-card); padding: 2.5rem; border-radius: 12px; width: 90%; max-width: 450px;
            text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            color: var(--text-main);
        }
        .modal-icon { font-size: 4rem; margin-bottom: 1.5rem; }
        .modal-icon.success { color: #10b981; }
        .modal-icon.error { color: #ef4444; }
        .modal-title { font-size: 1.5rem; font-weight: bold; margin-bottom: 0.75rem; color: var(--text-main); }
        .modal-text { font-size: 1.1rem; color: var(--text-muted); margin-bottom: 2rem; line-height: 1.5; }
        .modal-btn {
            padding: 0.8rem 2.5rem; border: none; border-radius: 8px; font-weight: bold; font-size: 1rem; cursor: pointer;
            background: var(--primary); color: white; transition: background 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .modal-btn:hover { background: #2563eb; transform: translateY(-1px); }

        .filter-bar { display: flex; gap: 1rem; margin-bottom: 1.5rem; align-items: center; }
        .filter-bar input {
            flex: 1; max-width: 400px; padding: 0.6rem 1rem; border: 1px solid var(--border);
            border-radius: 8px; background: var(--bg-input); color: var(--text-main); outline: none;
        }
        .filter-bar input:focus { border-color: var(--primary); }

        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th, td { text-align: left; padding: 0.75rem 1rem; border-bottom: 1px solid var(--border); color: var(--text-main); }
        th { background-color: var(--bg-main); font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.03em; color: var(--text-muted); }
        tr:hover { background-color: var(--bg-hover); }
        
        .type-badge {
            padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;
        }
        .type-badge.folder { background: rgba(251,191,36,0.15); color: #d97706; }
        .type-badge.file { background: rgba(239,68,68,0.1); color: #ef4444; }

        .pagination { display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 1.5rem; }
        .pagination button {
            padding: 0.5rem 0.9rem; border: 1px solid var(--border); border-radius: 6px;
            background: var(--bg-card); color: var(--text-main); cursor: pointer; font-size: 0.85rem;
        }
        .pagination button:hover:not(:disabled) { background: var(--bg-hover); border-color: var(--primary); color: var(--primary); }
        .pagination button:disabled { opacity: 0.4; cursor: not-allowed; }
        .pagination button.active { background: var(--primary); color: white; border-color: var(--primary); }
        .pagination span { color: var(--text-muted); font-size: 0.85rem; }

        .path-cell { max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: 'Courier New', monospace; font-size: 0.8rem; color: var(--text-muted); }
        .size-cell { white-space: nowrap; }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="header-left"><img src="./assets/img/pgim booking.png" alt="PGIM Logo"></div>
        <div class="header-right">
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                <button id="sebToggleBtn" data-enabled="<?php echo $safe_browser_only ? 'true' : 'false'; ?>" style="padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.75rem; font-weight: bold; cursor: pointer; border: none; margin-right: 15px; background: <?php echo $safe_browser_only ? '#10b981' : '#ef4444'; ?>; color: white; transition: background 0.2s;">
                    <?php echo $safe_browser_only ? 'SEB OFF' : 'SEB ON'; ?>
                </button>
            <?php endif; ?>
            <button id="themeToggle" class="theme-toggle" title="Toggle Theme">
                <div class="theme-toggle-knob"><i class="fas fa-sun"></i></div>
            </button>
            <a href="profile.php" style="text-decoration: none; color: inherit;">
                <div class="user-profile"><i class="fas fa-user-circle"></i>
                    <div class="user-info-text"><span class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span></div>
                </div>
            </a>
            <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <div class="app-container">
        <div class="watermark">PGIM LIBRARY<br><p><span><?php echo htmlspecialchars($_SESSION['email']); ?></span>
            <?php echo "ID: " . htmlspecialchars($_SESSION['id_number']) . " <br> SLMC: " . htmlspecialchars($_SESSION['slmc_number']); ?></p>
        </div>

        <aside class="sidebar">
            <div class="sidebar-header"><h2>PGIM Digital Library</h2></div>
            <nav class="sidebar-nav">
                <ul>
                    <li onclick="window.location.href='index.php'"><i class="fas fa-home"></i> <span>Home</span></li>
                    <li style="margin-top: 1rem; padding-left: 1.5rem; font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; cursor: default; pointer-events: none; border: none;">Admin Panel</li>
                    <li class="<?= $activePage == 'pending_users' ? 'active' : '' ?>" onclick="window.location.href='admin_pending_users.php'">
                        <i class="fas fa-user-clock"></i> <span>Pending Activations</span>
                        <?php if ($pending_count > 0): ?>
                            <span style="background: #ef4444; color: white; padding: 2px 6px; border-radius: 10px; font-size: 0.7rem; margin-left: auto;"><?= $pending_count ?></span>
                        <?php endif; ?>
                    </li>
                    <li class="<?= $activePage == 'create_user' ? 'active' : '' ?>" onclick="window.location.href='admin_create_user.php'"><i class="fas fa-user-plus"></i> <span>Add New User</span></li>
                    <li class="<?= $activePage == 'all_users' ? 'active' : '' ?>" onclick="window.location.href='admin_users.php'"><i class="fas fa-users"></i> <span>All Users</span></li>
                    <li class="<?= $activePage == 'activity' ? 'active' : '' ?>" onclick="window.location.href='admin_activity.php'"><i class="fas fa-history"></i> <span>Library Analytics</span></li>
                    <li class="<?= $activePage == 'reindex' ? 'active' : '' ?>" onclick="window.location.href='admin_reindex.php'"><i class="fas fa-database"></i> <span>Search Index</span></li>
                    <li onclick="window.location.href='profile.php'"><i class="fas fa-id-card"></i> <span>My Profile</span></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <p>&copy; 2026 PGIM Library -V1.2</p>
                <p>Developed by PGIM IT Unit - Dushan</p>
            </div>
        </aside>

        <main class="main-content">
            <div class="content-view">
                <div class="view-header"><h1>Search Index Manager</h1></div>

                <div class="admin-card">
                    <div class="index-header">
                        <h2><i class="fas fa-database"></i> File Index</h2>
                        <button class="btn-reindex" id="btnReindex" onclick="runReindex()">
                            <i class="fas fa-sync-alt" id="reindexIcon"></i> Re-Index Library
                        </button>
                    </div>

                    <div class="status-toast" id="statusToast"></div>

                    <div class="stats-row" id="statsRow">
                        <div class="stat-pill"><i class="fas fa-file"></i> Total Indexed: <strong id="statTotal">-</strong></div>
                        <div class="stat-pill"><i class="fas fa-pager"></i> Page: <strong id="statPage">-</strong></div>
                    </div>

                    <div class="filter-bar">
                        <input type="text" id="filterInput" placeholder="Filter indexed files... (Press Enter)">
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>File Name</th>
                                <th>Virtual Path</th>
                                <th>Size</th>
                            </tr>
                        </thead>
                        <tbody id="indexTableBody">
                            <tr><td colspan="4" style="text-align:center; padding: 3rem; color: var(--text-muted);">Loading...</td></tr>
                        </tbody>
                    </table>

                    <div class="pagination" id="pagination"></div>
                </div>
            </div>
        </main>
    </div>

    <!-- Result Modal -->
    <div class="modal-overlay" id="resultModal">
        <div class="modal-content">
            <div id="modalIcon" class="modal-icon success"><i class="fas fa-check-circle"></i></div>
            <div class="modal-title" id="modalTitle">Indexing Complete</div>
            <div class="modal-text" id="modalMessage">Successfully indexed files.</div>
            <button class="modal-btn" onclick="closeModal()">OK, Got it</button>
        </div>
    </div>

    <script>
        let currentPage = 1;
        let currentFilter = '';

        async function loadIndex(page = 1, search = '') {
            currentPage = page;
            currentFilter = search;
            const tbody = document.getElementById('indexTableBody');
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:2rem; color:var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';

            try {
                const resp = await fetch(`reindex.php?action=list&page=${page}&search=${encodeURIComponent(search)}`);
                const data = await resp.json();

                if (data.error) { tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#ef4444;">${data.error}</td></tr>`; return; }

                document.getElementById('statTotal').textContent = data.total.toLocaleString();
                document.getElementById('statPage').textContent = `${data.page} / ${data.totalPages}`;

                if (data.items.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:3rem; color:var(--text-muted);"><i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:0.5rem;"></i>No indexed files found. Click "Re-Index Library" to scan your drives.</td></tr>';
                    document.getElementById('pagination').innerHTML = '';
                    return;
                }

                tbody.innerHTML = data.items.map(item => {
                    const isDir = parseInt(item.is_dir);
                    const typeBadge = isDir ? '<span class="type-badge folder"><i class="fas fa-folder"></i> Folder</span>' : '<span class="type-badge file"><i class="fas fa-file-pdf"></i> PDF</span>';
                    const size = isDir ? '-' : formatSize(parseInt(item.file_size));
                    return `<tr>
                        <td>${typeBadge}</td>
                        <td title="${escapeHtml(item.file_name)}">${escapeHtml(item.file_name)}</td>
                        <td class="path-cell" title="${escapeHtml(item.virtual_path)}">${escapeHtml(item.virtual_path)}</td>
                        <td class="size-cell">${size}</td>
                    </tr>`;
                }).join('');

                renderPagination(data.page, data.totalPages);
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#ef4444;">Error loading index</td></tr>';
            }
        }

        function renderPagination(current, total) {
            const el = document.getElementById('pagination');
            if (total <= 1) { el.innerHTML = ''; return; }

            let html = `<button ${current <= 1 ? 'disabled' : ''} onclick="loadIndex(${current - 1}, currentFilter)"><i class="fas fa-chevron-left"></i></button>`;

            const range = 2;
            let start = Math.max(1, current - range);
            let end = Math.min(total, current + range);

            if (start > 1) { html += `<button onclick="loadIndex(1, currentFilter)">1</button>`; if (start > 2) html += '<span>...</span>'; }
            for (let i = start; i <= end; i++) {
                html += `<button class="${i === current ? 'active' : ''}" onclick="loadIndex(${i}, currentFilter)">${i}</button>`;
            }
            if (end < total) { if (end < total - 1) html += '<span>...</span>'; html += `<button onclick="loadIndex(${total}, currentFilter)">${total}</button>`; }

            html += `<button ${current >= total ? 'disabled' : ''} onclick="loadIndex(${current + 1}, currentFilter)"><i class="fas fa-chevron-right"></i></button>`;
            el.innerHTML = html;
        }

        function closeModal() {
            document.getElementById('resultModal').style.display = 'none';
        }

        async function runReindex() {
            const btn = document.getElementById('btnReindex');
            const icon = document.getElementById('reindexIcon');

            btn.disabled = true;
            icon.className = 'fas fa-sync-alt fa-spin';
            btn.innerHTML = '<i class="fas fa-sync-alt fa-spin" id="reindexIcon"></i> Indexing... (Please wait)';
            
            // Hide old toast if it was there
            document.getElementById('statusToast').style.display = 'none';

            try {
                const resp = await fetch('reindex.php?action=run');
                const data = await resp.json();
                
                const modal = document.getElementById('resultModal');
                const modalIcon = document.getElementById('modalIcon');
                const modalTitle = document.getElementById('modalTitle');
                const modalMessage = document.getElementById('modalMessage');

                if (data.success) {
                    modalIcon.className = 'modal-icon success';
                    modalIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
                    modalTitle.textContent = 'Indexing Complete!';
                    modalMessage.textContent = data.message;
                    loadIndex(1, currentFilter);
                } else {
                    modalIcon.className = 'modal-icon error';
                    modalIcon.innerHTML = '<i class="fas fa-exclamation-circle"></i>';
                    modalTitle.textContent = 'Indexing Error';
                    modalMessage.textContent = data.error || 'Unknown error occurred.';
                }
                modal.style.display = 'flex';
            } catch (e) {
                const modal = document.getElementById('resultModal');
                document.getElementById('modalIcon').className = 'modal-icon error';
                document.getElementById('modalIcon').innerHTML = '<i class="fas fa-wifi"></i>';
                document.getElementById('modalTitle').textContent = 'Network Error';
                document.getElementById('modalMessage').textContent = 'A network error or timeout occurred during indexing.';
                modal.style.display = 'flex';
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sync-alt" id="reindexIcon"></i> Re-Index Library';
        }

        function formatSize(bytes) {
            if (!bytes || bytes === 0) return '-';
            const units = ['B', 'KB', 'MB', 'GB'];
            let i = 0;
            while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
            return bytes.toFixed(i > 0 ? 1 : 0) + ' ' + units[i];
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        // Filter on Enter key
        document.getElementById('filterInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                loadIndex(1, this.value.trim());
            }
        });

        // Initial load
        loadIndex(1);
    </script>
</body>
</html>
