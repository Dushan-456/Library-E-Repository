<?php
session_start();
require_once __DIR__ . '/seb_check.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/session_timeout.php';

require_once __DIR__ . '/path_config.php';

if (!isset($_GET['file'])) {
    die("No file specified");
}

$file = $_GET['file'];
$physicalPath = getPhysicalPath($file);

// Security Check
if (!isPathSecure($physicalPath, $file)) {
    die("Invalid file path");
}

$fullPath = realpath($physicalPath);
if ($fullPath === false || !is_file($fullPath)) {
    die("Invalid file path");
}

// Log Document Access
require_once __DIR__ . '/db_config.php';
try {
    $logStmt = $pdo->prepare("INSERT INTO document_access_logs (user_id, file_path) VALUES (?, ?)");
    $logStmt->execute([$_SESSION['user_id'], $file]);
} catch (PDOException $e) {
    // Silent fail for logging to ensure viewer still works
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure PDF Viewer - <?php echo htmlspecialchars(basename($file)); ?></title>
    <link rel="icon" type="image/x-icon" href="./assets/img/logo without bg.png">
    <!-- Local pdf.js for rendering PDFs natively -->
    <script src="./assets/pdfjs/pdf.min.js"></script>
    <!-- Local FontAwesome for Offline Support -->
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 320px;
            --top-bar-height: 48px;
            --sidebar-bg: #ced4da; /* Light grey like the image */
            --viewer-bg: #525659;
            --border: #adb5bd;
        }

        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--viewer-bg);
            overflow: hidden;
        }

        .viewer-layout {
            display: flex;
            height: 100vh;
            width: 100vw;
        }

        /* Sidebar Styling (Exact Match) */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            z-index: 20;
            overflow: hidden;
        }

        .sidebar-header {
            padding: 10px;
            display: flex;
            gap: 15px;
            background: #dee2e6;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-header i {
            color: #495057;
            cursor: pointer;
            font-size: 1.1rem;
        }

        .outline-container {
            flex: 1;
            overflow-y: auto;
            padding: 10px 5px;
        }

        .outline-tree {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .outline-item-wrap {
            display: flex;
            align-items: flex-start;
            padding: 4px 5px;
            cursor: pointer;
            font-size: 0.9rem;
            color: #212529;
            gap: 4px;
        }

        .outline-item-wrap:hover {
            background: rgba(0,0,0,0.05);
        }

        .outline-toggle {
            width: 16px;
            text-align: center;
            font-size: 0.75rem;
            color: #495057;
            margin-top: 3px;
        }

        .outline-content {
            flex: 1;
            white-space: normal;
            word-wrap: break-word;
        }

        .outline-children {
            list-style: none;
            padding-left: 20px;
            display: none;
        }

        .outline-children.expanded {
            display: block;
        }

        /* Search Panel Styling */
        .search-panel {
            display: none;
            flex-direction: column;
            height: 100%;
            padding: 10px;
        }

        .search-input-container {
            display: flex;
            background: white;
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 5px 10px;
            margin-bottom: 15px;
            align-items: center;
        }

        .search-input-container input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 0.9rem;
            padding: 5px;
        }

        .search-results {
            flex: 1;
            overflow-y: auto;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .search-result-item {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
            cursor: pointer;
            font-size: 0.85rem;
        }

        .search-result-item:hover {
            background: rgba(0,0,0,0.05);
        }

        .search-result-page {
            font-weight: bold;
            color: #007bff;
            margin-bottom: 3px;
            display: block;
        }

        .search-result-snippet {
            color: #495057;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .sidebar-header i.active {
            color: #007bff;
            background: rgba(0,123,255,0.1);
            border-radius: 4px;
            padding: 4px;
        }

        /* Continuous Scroll Viewport */
        .main-viewer {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .toolbar {
            height: var(--top-bar-height);
            background: #323639;
            color: white;
            display: flex;
            align-items: center;
            padding: 0 20px;
            gap: 20px;
            z-index: 30;
        }

        .toolbar button {
            background: transparent;
            border: none;
            color: #f1f3f4;
            cursor: pointer;
            font-size: 1.1rem;
            padding: 8px;
            border-radius: 50%;
        }

        .toolbar button:hover {
            background: rgba(255,255,255,0.1);
        }

        .pdf-viewport {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 0;
            scroll-behavior: smooth;
        }

        .page-container {
            margin-bottom: 25px;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            background: white;
            position: relative;
        }

        canvas {
            display: block;
        }

        /* Page Watermark Style */
        .page-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-40deg);
            font-size: 5rem;
            color: rgba(0,0,0,0.2);
            font-weight: 800;
            pointer-events: none;
            user-select: none;
            z-index: 5;
            white-space: pre-line;
            text-align: center;
            width: 100%;
            line-height: 1.4;
            text-transform: uppercase;
        }
        .page-watermark p {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) ;
            font-size: 3.5rem;
            color: rgba(0,0,0,0.12);
            font-weight: 800;
            pointer-events: none;
            user-select: none;
            z-index: 5;
            white-space: pre-line;
            text-align: center;
            width: 100%;
            line-height: 1.4;
            text-transform: uppercase;
        }

        .viewer-footer {
            height: 32px;
            background: #2a2d2e;
            color: #bdc1c6;
            display: flex;
            align-items: center;
            padding: 0 20px;
            font-size: 0.75rem;
            z-index: 30;
            border-top: 1px solid #3c4043;
        }

        .user-security-info {
           font-family: 'Courier New', Courier, monospace;
           font-size: 0.8rem;
           color: #8ab4f8;
           background: rgba(138, 180, 248, 0.1);
           padding: 4px 12px;
           border-radius: 4px;
           margin-left: 20px;
        }

        .filename-text {
            flex: 1;
            font-size: 0.95rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #page-indicator {
            background: rgba(0,0,0,0.3);
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        /* Night Mode (Color Inversion) */
        .night-mode .page-container {
            filter: invert(1) hue-rotate(180deg);
            box-shadow: 0 0 10px rgba(255,255,255,0.1) !important;
        }

        .night-mode canvas {
            opacity: 0.9; /* Slightly reduce brightness in dark mode */
        }

        .night-mode .page-watermark {
            color: rgba(184, 184, 184, 0.53) !important; /* Adjust watermark for night mode */
        }
        .night-mode .page-watermark p {
            color: rgba(184, 184, 184, 0.53) !important; /* Adjust watermark for night mode */
        }

        @media print {
            body { display: none !important; }
        }
    </style>
</head>
<body oncontextmenu="return false;">
    <div class="viewer-layout">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-list-ul active" id="btnShowOutline" title="Outline"></i>
                <i class="fas fa-search" id="btnShowSearch" title="Search"></i>
            </div>
            <div class="outline-container" id="outlineContainer">
                <ul class="outline-tree" id="outlineRoot"></ul>
            </div>
            <div class="search-panel" id="searchPanel">
                <div class="search-input-container">
                    <input type="text" id="pdfSearchInput" placeholder="Search in document...">
                    <i class="fas fa-search" style="color: #6c757d; font-size: 0.9rem;"></i>
                </div>
                <div id="searchStatus" style="font-size: 0.8rem; color: #6c757d; margin-bottom: 10px; display: none;"></div>
                <ul class="search-results" id="searchResults"></ul>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-viewer">
            <header class="toolbar">
                <button id="toggle-sidebar"><i class="fas fa-bars"></i></button>
                <span class="filename-text"><?php echo htmlspecialchars(basename($file)); ?></span>
                <div id="page-indicator">
                    <span id="current-page">1</span> / <span id="total-pages">0</span>
                </div>
                <div class="user-security-info">
                    <i class="fas fa-shield-alt"></i> <?php echo htmlspecialchars($_SESSION['email']); ?>
                </div>
                <div style="margin-left: auto; display: flex; gap: 10px;">
                    <button id="toggle-night-mode" title="Toggle Night Mode"><i class="fas fa-moon"></i></button>
                    <button id="zoom-out"><i class="fas fa-minus"></i></button>
                    <button id="zoom-in"><i class="fas fa-plus"></i></button>
                </div>
            </header>

            <div class="pdf-viewport" id="pdfViewport">
                <!-- Pages will be rendered here dynamically -->
            </div>

            <footer class="viewer-footer">
                <i class="fas fa-user-lock" style="margin-right: 8px;"></i>
                <span>Secure access granted to: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong> (SLMC: <?php echo htmlspecialchars($_SESSION['slmc_number']); ?>) | ID: <?php echo htmlspecialchars($_SESSION['id_number']); ?></span>
                <span style="margin-left: auto; opacity: 0.7;">PGIM DIGITAL LIBRARY SECURITY SYSTEM &copy; 2026</span>
            </footer>
        </main>
    </div>

    <script>
        const url = 'stream_pdf.php?file=<?php echo urlencode($file); ?>';
        const userInfo = {
            email: '<?php echo $_SESSION['email']; ?>',
            idNumber: '<?php echo $_SESSION['id_number']; ?>',
            slmcNumber: '<?php echo $_SESSION['slmc_number']; ?>'
        };
        const pdfViewport = document.getElementById('pdfViewport');
        const outlineRoot = document.getElementById('outlineRoot');
        const outlineContainer = document.getElementById('outlineContainer');
        const searchPanel = document.getElementById('searchPanel');
        const searchResults = document.getElementById('searchResults');
        const pdfSearchInput = document.getElementById('pdfSearchInput');
        const searchStatus = document.getElementById('searchStatus');
        
        let pdfDoc = null;
        let scale = 1.3;
        let pagesToRender = new Set();
        let renderedPages = new Set();
        // Initialize PDF.js worker locally for offline support
        pdfjsLib.GlobalWorkerOptions.workerSrc = './assets/pdfjs/pdf.worker.min.js';

        async function initViewer() {
            try {
                pdfDoc = await pdfjsLib.getDocument(url).promise;
                document.getElementById('total-pages').textContent = pdfDoc.numPages;
                
                // Create placeholders for all pages
                for (let i = 1; i <= pdfDoc.numPages; i++) {
                    const pageContainer = document.createElement('div');
                    pageContainer.id = `page-container-${i}`;
                    pageContainer.className = 'page-container';
                    pageContainer.dataset.pageNumber = i;
                    
                    // We need a sample page to get dimensions for the placeholder
                    const page = await pdfDoc.getPage(i);
                    const viewport = page.getViewport({ scale });
                    pageContainer.style.width = `${viewport.width}px`;
                    pageContainer.style.height = `${viewport.height}px`;
                    
                    pdfViewport.appendChild(pageContainer);
                    observer.observe(pageContainer);
                }

                const outline = await pdfDoc.getOutline();
                renderOutline(outline, outlineRoot);

            } catch (err) {
                console.error('Error loading PDF:', err);
            }
        }

        // Lazy Rendering Observer
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                const pageNum = parseInt(entry.target.dataset.pageNumber);
                if (entry.isIntersecting) {
                    if (!renderedPages.has(pageNum)) {
                        renderPage(pageNum);
                    }
                    // Update current page in toolbar
                    document.getElementById('current-page').textContent = pageNum;
                }
            });
        }, { threshold: 0.1 });

        async function renderPage(num) {
            if (renderedPages.has(num)) return;
            renderedPages.add(num);

            const container = document.getElementById(`page-container-${num}`);
            const page = await pdfDoc.getPage(num);
            const viewport = page.getViewport({ scale });
            
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            container.appendChild(canvas);

            // Add Watermark
            const watermark = document.createElement('div');
            watermark.className = 'page-watermark';
            watermark.innerHTML = `PGIM LIBRARY<br>
            <p>
            ${userInfo.email}<br>ID: ${userInfo.idNumber}<br>SLMC: ${userInfo.slmcNumber}
            </p>`;
            container.appendChild(watermark);

            await page.render({
                canvasContext: ctx,
                viewport: viewport
            }).promise;
        }

        function renderOutline(outline, container) {
            if (!outline || outline.length === 0) {
                container.innerHTML = '<li style="padding: 15px; font-size: 0.85rem; color: #666;">No outline</li>';
                return;
            }

            outline.forEach(item => {
                const li = document.createElement('li');
                const hasChildren = item.items && item.items.length > 0;
                
                const wrap = document.createElement('div');
                wrap.className = 'outline-item-wrap';
                wrap.innerHTML = `
                    <div class="outline-toggle">${hasChildren ? '<i class="fas fa-caret-down"></i>' : ''}</div>
                    <div class="outline-content">${item.title}</div>
                `;

                li.appendChild(wrap);

                if (hasChildren) {
                    const childUl = document.createElement('ul');
                    childUl.className = 'outline-children expanded';
                    renderOutline(item.items, childUl);
                    li.appendChild(childUl);

                    wrap.querySelector('.outline-toggle').onclick = (e) => {
                        e.stopPropagation();
                        const isExpanded = childUl.classList.toggle('expanded');
                        wrap.querySelector('.outline-toggle i').className = isExpanded ? 'fas fa-caret-down' : 'fas fa-caret-right';
                    };
                }

                wrap.onclick = async () => {
                    let dest = item.dest;
                    if (typeof dest === 'string') {
                        dest = await pdfDoc.getDestination(dest);
                    }
                    
                    if (dest && Array.isArray(dest)) {
                        try {
                            const pageIdx = await pdfDoc.getPageIndex(dest[0]);
                            const targetPage = document.getElementById(`page-container-${pageIdx + 1}`);
                            if (targetPage) {
                                pdfViewport.scrollTo({
                                    top: targetPage.offsetTop - 10,
                                    behavior: 'smooth'
                                });
                            }
                        } catch (err) {
                            console.error('Error navigating to destination:', err);
                        }
                    }
                };

                container.appendChild(li);
            });
        }

        // Toolbar Events
        document.getElementById('zoom-in').onclick = () => { scale += 0.2; refreshAllPages(); };
        document.getElementById('zoom-out').onclick = () => { if (scale > 0.5) scale -= 0.2; refreshAllPages(); };
        document.getElementById('toggle-sidebar').onclick = () => {
            const sidebar = document.getElementById('sidebar');
            sidebar.style.display = sidebar.style.display === 'none' ? 'flex' : 'none';
        }

        async function refreshAllPages() {
            renderedPages.clear();
            pdfViewport.innerHTML = '';
            await initViewer();
        }

        // Tab Switching
        document.getElementById('btnShowOutline').onclick = function() {
            this.classList.add('active');
            document.getElementById('btnShowSearch').classList.remove('active');
            outlineContainer.style.display = 'block';
            searchPanel.style.display = 'none';
        };

        document.getElementById('btnShowSearch').onclick = function() {
            this.classList.add('active');
            document.getElementById('btnShowOutline').classList.remove('active');
            outlineContainer.style.display = 'none';
            searchPanel.style.display = 'flex';
            pdfSearchInput.focus();
        };

        // PDF Search Logic
        let searchTimeout;
        pdfSearchInput.oninput = () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(executeSearch, 500);
        };

        async function executeSearch() {
            const query = pdfSearchInput.value.trim().toLowerCase();
            searchResults.innerHTML = '';
            
            if (query.length < 3) {
                searchStatus.style.display = 'none';
                return;
            }

            searchStatus.textContent = 'Searching...';
            searchStatus.style.display = 'block';

            let matches = 0;
            for (let i = 1; i <= pdfDoc.numPages; i++) {
                const page = await pdfDoc.getPage(i);
                const textContent = await page.getTextContent();
                const text = textContent.items.map(item => item.str).join(' ');
                
                if (text.toLowerCase().includes(query)) {
                    const index = text.toLowerCase().indexOf(query);
                    const snippet = text.substring(Math.max(0, index - 40), Math.min(text.length, index + 60));
                    
                    const li = document.createElement('li');
                    li.className = 'search-result-item';
                    li.innerHTML = `
                        <span class="search-result-page">Page ${i}</span>
                        <div class="search-result-snippet">...${snippet}...</div>
                    `;
                    li.onclick = () => {
                        const targetPage = document.getElementById(`page-container-${i}`);
                        if (targetPage) {
                            pdfViewport.scrollTo({
                                top: targetPage.offsetTop - 10,
                                behavior: 'smooth'
                            });
                        }
                    };
                    searchResults.appendChild(li);
                    matches++;
                }
                
                if (matches > 50) break; // Limit results
            }

            searchStatus.textContent = matches === 0 ? 'No matches found' : `Found ${matches} matches`;
        }

        initViewer();

        // Night Mode Logic
        const btnNightMode = document.getElementById('toggle-night-mode');
        const isNightMode = localStorage.getItem('nightMode') === 'true';

        if (isNightMode) {
            document.body.classList.add('night-mode');
            btnNightMode.innerHTML = '<i class="fas fa-sun"></i>';
        }

        btnNightMode.onclick = () => {
            const active = document.body.classList.toggle('night-mode');
            localStorage.setItem('nightMode', active);
            btnNightMode.innerHTML = active ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        };

        // Security
        window.oncontextmenu = () => false;
        window.onkeydown = (e) => {
            if ((e.ctrlKey || e.metaKey) && ['p', 's', 'u'].includes(e.key.toLowerCase())) e.preventDefault();
        };
    </script>
</body>
</html>
