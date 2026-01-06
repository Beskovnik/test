from playwright.sync_api import sync_playwright, expect
import time

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(viewport={'width': 1280, 'height': 800})
        page = context.new_page()

        # Capture console logs
        page.on("console", lambda msg: print(f"BROWSER CONSOLE: {msg.text}"))

        # 1. Login as Admin
        print("Logging in...")
        page.goto("http://localhost:8085/login.php")
        page.fill("input[name='identifier']", "koble")
        page.fill("input[name='password']", "Matiden1")
        page.click("button[type='submit']")
        page.wait_for_url("**/index.php")

        # 2. Configure Settings (Ensure ARSO is set)
        print("Configuring ARSO...")
        page.goto("http://localhost:8085/admin/settings.php")
        page.fill("input[name='weather_arso_location']", "Ljubljana")
        page.fill("input[name='weather_arso_station_id']", "LJUBL-ANA_BEZIGRAD")
        page.click("button[type='submit']")
        time.sleep(1)

        # 3. Visit Weather Page
        print("Visiting Weather Page...")
        page.goto("http://localhost:8085/weather.php")

        # 4. Verify Content
        print("Verifying content...")

        # Check if error state appears
        try:
            if page.is_visible("#error-state"):
                err_msg = page.text_content("#error-msg")
                print(f"ERROR ON PAGE: {err_msg}")
        except:
            pass

        # Check for location title
        expect(page.get_by_text("Ljubljana")).to_be_visible()

        # Wait for data load (extended timeout)
        try:
            page.wait_for_selector("#weather-dashboard", state="visible", timeout=20000)
        except Exception as e:
            print("Dashboard not visible after timeout.")
            # Dump HTML for debugging
            print(page.content())
            raise e

        # Check stats
        expect(page.get_by_text("Temperatura")).to_be_visible()
        expect(page.locator("#current-stats").get_by_text("Veter")).to_be_visible()

        # Check chart canvas
        expect(page.locator("#weatherChart")).to_be_visible()

        # Check table
        expect(page.locator("#forecast-table-body tr").first).to_be_visible()

        # Screenshot
        print("Taking screenshot...")
        page.screenshot(path="verification/weather_arso_verified.png")

        browser.close()

if __name__ == "__main__":
    run()
