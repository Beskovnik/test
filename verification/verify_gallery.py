import os
import re
from playwright.sync_api import sync_playwright, expect

def test_gallery_share():
    cwd = os.getcwd()
    file_path = f"file://{cwd}/verification/test_gallery.html"

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Mock fetch globally because file:// protocol has issues with real fetch and interception
        page.add_init_script("""
            window.fetch = async (url, options) => {
                console.log('Mock Fetch Called:', url);
                if (url.includes('/api/share/create.php')) {
                    return {
                        ok: true,
                        json: async () => ({
                            success: true,
                            share_url: '/share.php?token=mocked_token'
                        })
                    };
                }
                throw new Error('Unexpected fetch: ' + url);
            };
        """)

        # Listen to console to debug
        page.on("console", lambda msg: print(f"PAGE LOG: {msg.text}"))

        print(f"Navigating to {file_path}")
        page.goto(file_path)

        # 1. Activate Select Mode
        print("Activating Select Mode...")
        page.locator("#toggle-select-mode").click()

        # 2. Select Cards
        print("Selecting cards...")
        page.locator('.gallery-card[data-id="101"]').click()
        page.locator('.gallery-card[data-id="102"]').click()

        # 3. Click Share
        print("Clicking Bulk Share...")
        page.locator("#bulk-share-btn").click()

        # 4. Verify Modal Appears
        print("Waiting for modal...")
        modal = page.locator(".bulk-share-results")
        expect(modal).to_be_visible()

        # 5. Verify Content
        print("Verifying modal content...")
        expect(page.locator(".bulk-share-header h3")).to_have_text("Zbirka ustvarjena")

        input_el = page.locator(".share-link-input")
        expect(input_el).to_be_visible()
        value = input_el.input_value()
        print(f"Generated URL in input: {value}")

        if "mocked_token" not in value:
             raise Exception("Token not found in generated URL")

        # 6. Take Screenshot
        print("Taking screenshot...")
        screenshot_path = f"{cwd}/verification/gallery_share_verified.png"
        page.screenshot(path=screenshot_path, full_page=True)
        print(f"Screenshot saved to {screenshot_path}")

        browser.close()

if __name__ == "__main__":
    test_gallery_share()
