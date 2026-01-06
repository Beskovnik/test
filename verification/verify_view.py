from playwright.sync_api import sync_playwright

def verify_view_page():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Navigate to the test post created (ID 4)
        page.goto("http://localhost:8080/view.php?id=4")

        # Check if the JS file is loaded
        # We can check network requests or just check if functionality works
        # I'll check if view count updates (it starts at 10, should become 11)

        # Wait for potential async update
        page.wait_for_timeout(2000)

        # Get view count text
        view_count_text = page.locator("#viewCount").inner_text()
        print(f"View count text: {view_count_text}")

        page.screenshot(path="verification/view_page.png")
        browser.close()

if __name__ == "__main__":
    verify_view_page()
