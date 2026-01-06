<?php
// Mock PHP script to verify the UI styling of the upload list items.
// It loads the same layout and CSS as the real page, but manually injects dummy items.

require __DIR__ . '/../app/Bootstrap.php';
require __DIR__ . '/../includes/layout.php';

// Mock user for Auth::requireLogin() if needed, but since we are just rendering HTML,
// we can bypass it or rely on the session if active.
// For verification script, we will just manually include header/footer.

// Include CSS
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Upload UI</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        body { padding: 2rem; }
    </style>
</head>
<body>

<div class="admin-page">
    <div class="card" style="padding: 2rem;">
        <div class="uploader-container">
            <div class="upload-drop-zone" id="dropZone">
                <div class="drop-content">
                    <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <polyline points="17 8 12 3 7 8" />
                        <line x1="12" y1="3" x2="12" y2="15" />
                    </svg>
                    <h2 class="upload-title">Povleči datoteke sem</h2>
                    <p class="upload-subtitle">ali tapni za izbor (max 100)</p>
                </div>
                <div class="upload-limits-text">
                    Max slika: 5.0 GB • Max video: 5.0 GB • Max: 100 datotek
                </div>
            </div>

            <div class="upload-list" id="uploadList">
                <!-- Mock Item 1: Uploading -->
                <div class="upload-item uploading">
                    <div class="file-header">
                        <div class="file-info-main">
                            <div class="file-type-icon">🖼️</div>
                            <div class="file-details-text">
                                <span class="file-name" title="Vacation_Photo_2024.jpg">Vacation_Photo_2024.jpg</span>
                                <span class="file-size">4.2 MB</span>
                            </div>
                        </div>
                        <div class="upload-status-badge">Nalaganje...</div>
                    </div>
                    <div class="file-progress-wrapper">
                        <div class="progress-bar-track">
                            <div class="progress-bar-fill" style="width: 45%"></div>
                        </div>
                        <div class="progress-stats">
                            <span class="stats-percent">45%</span>
                            <span class="stats-speed">12.5 MB/s</span>
                            <span class="stats-eta">3s</span>
                        </div>
                    </div>
                    <button class="progress-cancel-btn" title="Prekliči">✕</button>
                </div>

                <!-- Mock Item 2: Success -->
                <div class="upload-item success">
                    <div class="file-header">
                        <div class="file-info-main">
                            <div class="file-type-icon">🎬</div>
                            <div class="file-details-text">
                                <span class="file-name" title="Funny_Cat_Video.mp4">Funny_Cat_Video.mp4</span>
                                <span class="file-size">155.39 KB</span>
                            </div>
                        </div>
                        <div class="upload-status-badge">Končano</div>
                    </div>
                    <div class="file-progress-wrapper">
                        <div class="progress-bar-track">
                            <div class="progress-bar-fill" style="width: 100%"></div>
                        </div>
                        <div class="progress-stats">
                            <span class="stats-percent">100%</span>
                            <span class="stats-speed"></span>
                            <span class="stats-eta">Uspešno</span>
                        </div>
                    </div>
                    <button class="progress-cancel-btn" title="Prekliči" disabled>✓</button>
                </div>

                 <!-- Mock Item 3: Error -->
                 <div class="upload-item error">
                    <div class="file-header">
                        <div class="file-info-main">
                            <div class="file-type-icon">📄</div>
                            <div class="file-details-text">
                                <span class="file-name" title="Document.pdf">Document.pdf</span>
                                <span class="file-size">2.1 MB</span>
                            </div>
                        </div>
                        <div class="upload-status-badge">Napaka</div>
                    </div>
                    <div class="file-progress-wrapper">
                        <div class="progress-bar-track">
                            <div class="progress-bar-fill" style="width: 10%"></div>
                        </div>
                        <div class="progress-stats">
                            <span class="stats-percent">10%</span>
                            <span class="stats-speed"></span>
                            <span class="stats-eta">Invalid file type</span>
                        </div>
                    </div>
                    <button class="progress-cancel-btn" title="Prekliči">✕</button>
                </div>

            </div>
        </div>
    </div>
</div>

</body>
</html>
