import asyncio
from playwright.async_api import async_playwright

async def inspect_login():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        page = await browser.new_page()
        await page.goto("https://apps.eduportal.pk/Production/")
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(2000)
        
        content = await page.content()
        with open("E:/XAMPP/htdocs/HIIFI LMS/login_page.html", "w", encoding="utf-8") as f:
            f.write(content)
        print("Login page saved:", len(content), "bytes")
        
        # Also list all input/button elements
        elements = await page.evaluate("""() => {
            const results = [];
            document.querySelectorAll('input, button, form').forEach((el) => {
                results.push({
                    tag: el.tagName,
                    type: el.type || '',
                    name: el.name || '',
                    id: el.id || '',
                    placeholder: el.placeholder || '',
                    class: el.className || '',
                    value: el.value || ''
                });
            });
            return results;
        }""")
        for el in elements:
            print(el)
        
        await browser.close()

if __name__ == "__main__":
    asyncio.run(inspect_login())
