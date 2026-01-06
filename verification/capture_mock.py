from playwright.sync_api import sync_playwright

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()
        # Ensure server is running on localhost:8081
        try:
            page.goto("http://localhost:8081/verification/verify_upload_ui.php")
            page.wait_for_selector(".upload-item")
            page.screenshot(path="mock_upload_ui.png", full_page=True)
            print("Screenshot saved to mock_upload_ui.png")
        except Exception as e:
            print(f"Error: {e}")
        finally:
            browser.close()

if __name__ == "__main__":
    run()
