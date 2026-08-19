const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch();
    const page = await browser.newPage();
    
    page.on('console', msg => console.log('PAGE LOG:', msg.text()));
    page.on('pageerror', error => console.log('PAGE ERROR:', error.message));
    page.on('requestfailed', request => console.log('REQUEST FAILED:', request.url(), request.failure().errorText));

    // Try to login as coordinator
    await page.goto('http://localhost/NGO-donation-management-system/login.php');
    await page.type('input[name="email"]', 'coordinator@example.com'); // We might need correct credentials
    await page.type('input[name="password"]', 'password123'); // Assuming standard password
    await page.click('button[type="submit"]');
    await page.waitForNavigation();

    // Now go to coordinator_tasks
    await page.goto('http://localhost/NGO-donation-management-system/coordinator_tasks.php');
    console.log('Navigated to coordinator tasks');
    
    // Click assign task
    try {
        await page.click('button.btn-primary');
        console.log('Clicked assign task button');
        // wait for modal to become visible
        await new Promise(r => setTimeout(r, 1000));
        
        const isModalVisible = await page.evaluate(() => {
            const modal = document.getElementById('createModal');
            return modal && window.getComputedStyle(modal).visibility === 'visible';
        });
        console.log('Modal is visible:', isModalVisible);
    } catch (e) {
        console.log('Error clicking button:', e.message);
    }

    await browser.close();
})();
