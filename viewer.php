<?php
session_start();
require_once __DIR__ . '/seb_check.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// 15-Minute Session Timeout (900 seconds)
$timeout_duration = 900;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: login.php?msg=timeout");
    exit;
}
$_SESSION['last_activity'] = time(); // Update last activity time

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure PDF Viewer - <?php echo htmlspecialchars(basename($file)); ?></title>
    <link rel="icon" type="image/x-icon" href="./assets/img/logo without bg.png">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 5rem;
            color: rgba(0,0,0,0.2);
            font-weight: bold;
            pointer-events: none;
            user-select: none;
            z-index: 5;
            white-space: nowrap;
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
                <i class="fas fa-th-large" title="Thumbnails"></i>
                <i class="fas fa-list-ul" title="Outline"></i>
                <i class="fas fa-search" title="Search"></i>
            </div>
            <div class="outline-container" id="outlineContainer">
                <ul class="outline-tree" id="outlineRoot"></ul>
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
                <div style="margin-left: auto;">
                    <button id="zoom-out"><i class="fas fa-minus"></i></button>
                    <button id="zoom-in"><i class="fas fa-plus"></i></button>
                </div>
            </header>

            <div class="pdf-viewport" id="pdfViewport">
                <!-- Pages will be rendered here dynamically -->
            </div>
        </main>
    </div>

    <script>
        const url = 'stream_pdf.php?file=<?php echo urlencode($file); ?>';
        const pdfViewport = document.getElementById('pdfViewport');
        const outlineRoot = document.getElementById('outlineRoot');
        
        let pdfDoc = null;
        let scale = 1.3;
        let pagesToRender = new Set();
        let renderedPages = new Set();

        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

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
            watermark.textContent = 'PGIM LIBRARY';
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
                    if (item.dest) {
                        const dest = await pdfDoc.getDestination(item.dest);
                        const pageIdx = await pdfDoc.getPageIndex(dest[0]);
                        const targetPage = document.getElementById(`page-container-${pageIdx + 1}`);
                        if (targetPage) {
                            pdfViewport.scrollTo({
                                top: targetPage.offsetTop - 10,
                                behavior: 'smooth'
                            });
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

        initViewer();

        // Security
        window.oncontextmenu = () => false;
        window.onkeydown = (e) => {
            if ((e.ctrlKey || e.metaKey) && ['p', 's', 'u'].includes(e.key.toLowerCase())) e.preventDefault();
        };
    </script>
</body>
</html>
