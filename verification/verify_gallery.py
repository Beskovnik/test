import os
import re
from playwright.sync_api import sync_playwright, expect

def test_gallery_features():
    cwd = os.getcwd()
    file_path = f"file://{cwd}/verification/test_gallery.html"

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        print(f"Navigating to {file_path}")
        page.goto(file_path)

        # 1. Verify initial state (Toolbar hidden)
        print("Verifying initial state...")
        toolbar = page.locator("#gallery-toolbar")
        # Note: .gallery-toolbar has display:none in CSS, so it shouldn't be visible.
        # Playwright considers display:none as not visible.
        expect(toolbar).not_to_be_visible()

        # 2. Toggle Select Mode
        print("Activating Select Mode...")
        page.locator("#toggle-select-mode").click()
        expect(toolbar).to_be_visible()

        # Check body class
        # We need to wait a bit or verify class presence
        body = page.locator("body")
        expect(body).to_have_class(re.compile(r"select-mode"))

        # 3. Select a Card
        print("Selecting Card 101...")
        card1 = page.locator('.gallery-card[data-id="101"]')
        card1.click()

        # Verify selection state
        expect(card1).to_have_class(re.compile(r"selected"))
        expect(page.locator("#selected-count")).to_have_text("Izbrano: 1")

        # 4. Select another Card
        print("Selecting Card 102...")
        card2 = page.locator('.gallery-card[data-id="102"]')
        card2.click()
        expect(page.locator("#selected-count")).to_have_text("Izbrano: 2")

        # 5. Take Screenshot
        print("Taking screenshot...")
        screenshot_path = f"{cwd}/verification/gallery_verified.png"
        page.screenshot(path=screenshot_path, full_page=True)
        print(f"Screenshot saved to {screenshot_path}")

        browser.close()

if __name__ == "__main__":
    test_gallery_features()
