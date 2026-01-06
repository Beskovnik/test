from playwright.sync_api import sync_playwright

def test_weather_page():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Navigate to Weather page
        print("Navigating to Weather page...")
        page.goto("http://localhost:8085/weather.php")

        # Take screenshot of initial state (should show 'No configuration' or similar since we haven't set it up via UI yet)
        print("Taking initial screenshot...")
        page.screenshot(path="/home/jules/verification/weather_initial.png")

        # Login as admin to access settings
        print("Logging in as admin...")
        page.goto("http://localhost:8085/login.php")
        page.fill('input[name="identifier"]', 'admin')
        page.fill('input[name="password"]', 'Password123')
        page.click('button[type="submit"]')

        # Navigate to Settings
        print("Navigating to Settings...")
        page.goto("http://localhost:8085/admin/settings.php")

        # Verify CKAN fields exist
        print("Verifying CKAN fields...")
        page.wait_for_selector('input[name="ckan_base_url"]')
        page.wait_for_selector('input[name="weather_resource_id"]')

        # Verify Search Modal
        print("Opening Search Modal...")
        page.click('button:has-text("🔍 Poišči Vir")')
        page.wait_for_selector('#ckan-modal', state='visible')

        # Take screenshot of settings
        page.screenshot(path="/home/jules/verification/weather_settings.png")

        browser.close()

if __name__ == "__main__":
    test_weather_page()
