import os
from playwright.sync_api import sync_playwright, expect

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()

    # Read app.js content
    with open("assets/js/app.js", "r") as f:
        app_js_content = f.read()
        app_js_content = "console.log('App JS Loaded');\n" + app_js_content

    # Mock HTML content
    html_content = """
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="csrf-token" content="mock-token">
        <title>Mock View</title>
        <style>
            .toast { display: block; border: 1px solid red; padding: 10px; }
            .active { color: red; }
        </style>
    </head>
    <body>
        <div id="viewCount">👁️ 100</div>

        <button id="likeBtn" data-id="123">
            <span class="like-icon">🤍</span>
            <span class="like-label">Všečkaj</span>
            <span class="like-count">0</span>
        </button>

        <button id="shareBtn" data-url="/view.php?s=123">Deli</button>
        <button id="deleteBtn" data-id="123">Izbriši</button>

        <section id="commentsSection" data-id="123">
            <div id="commentList"></div>
            <form id="commentForm">
                <textarea name="body"></textarea>
                <button type="submit">Objavi</button>
            </form>
        </section>

        <script src="/assets/js/app.js"></script>
    </body>
    </html>
    """

    # Route request for the page itself
    page.route("http://localhost:8000/view.php", lambda route: route.fulfill(
        status=200,
        content_type="text/html",
        body=html_content
    ))

    # Route request for app.js (using wildcard to be sure)
    page.route("**/*.js", lambda route: route.fulfill(
        status=200,
        content_type="application/javascript",
        body=app_js_content
    ))

    # Route API requests
    page.route("**/api/view.php", lambda route: route.fulfill(
        status=200,
        content_type="application/json",
        body='{"ok": true, "views": 101}'
    ))

    page.route("**/api/like.php", lambda route: route.fulfill(
        status=200,
        content_type="application/json",
        body='{"ok": true, "liked": true, "count": 1}'
    ))

    page.route("**/api/comment_list.php*", lambda route: route.fulfill(
        status=200,
        content_type="application/json",
        body='{"ok": true, "comments": [{"author": "User", "body": "Test Comment", "created_at": 1600000000}]}'
    ))

    page.route("**/api/comment_add.php", lambda route: route.fulfill(
        status=200,
        content_type="application/json",
        body='{"ok": true}'
    ))

    # Handle console messages
    page.on("console", lambda msg: print(f"CONSOLE: {msg.text}"))

    # Handle dialogs
    page.on("dialog", lambda dialog: print(f"DIALOG: {dialog.message}"))

    # Navigate
    print("Navigating to view.php...")
    page.goto("http://localhost:8000/view.php")

    # Verify View Count Increment
    print("Verifying view count...")
    expect(page.locator("#viewCount")).to_contain_text("101")

    # Verify Like Button
    print("Verifying like button...")
    page.click("#likeBtn")
    expect(page.locator(".like-icon")).to_contain_text("❤️")
    expect(page.locator(".like-count")).to_contain_text("1")

    # Verify Comments Load
    print("Verifying comments...")
    expect(page.locator("#commentList")).to_contain_text("Test Comment")

    # Verify Share Button
    print("Verifying share button...")
    # Mock clipboard
    page.evaluate("""
        try {
            Object.defineProperty(navigator, 'clipboard', {
                value: {
                    writeText: async () => { console.log('Clipboard Write Mocked'); return Promise.resolve(); }
                },
                configurable: true
            });
        } catch (e) {
            console.error('Failed to mock clipboard', e);
        }
    """)
    page.click("#shareBtn")

    # Check for toast text
    print("Checking for toast...")
    expect(page.locator(".toast")).to_contain_text("Povezava kopirana!")

    # Screenshot
    print("Taking screenshot...")
    page.screenshot(path="verification/verification.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
