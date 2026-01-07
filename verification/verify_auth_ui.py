from playwright.sync_api import sync_playwright
import os

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context()
        page = context.new_page()

        # The PHP server is running on localhost:8080 (I will start it)
        base_url = "http://localhost:8080"

        # 1. Register Page
        try:
            print(f"Navigating to {base_url}/register.php")
            page.goto(f"{base_url}/register.php")
            page.wait_for_selector(".auth-page", timeout=5000) # Wait for the main container
            page.screenshot(path="verification/register_page.png")
            print("Captured register_page.png")
        except Exception as e:
            print(f"Error on register page: {e}")

        # 2. Login Page
        try:
            print(f"Navigating to {base_url}/login.php")
            page.goto(f"{base_url}/login.php")
            page.wait_for_selector(".auth-page", timeout=5000)
            page.screenshot(path="verification/login_page.png")
            print("Captured login_page.png")
        except Exception as e:
            print(f"Error on login page: {e}")

        # 3. Admin Boot Page
        try:
            print(f"Navigating to {base_url}/admin/boot.php")
            page.goto(f"{base_url}/admin/boot.php")
            # Might redirect if locked, but we'll see
            page.wait_for_load_state('networkidle')
            page.screenshot(path="verification/admin_boot_page.png")
            print("Captured admin_boot_page.png")
        except Exception as e:
            print(f"Error on admin boot page: {e}")

        browser.close()

if __name__ == "__main__":
    run()
