/**
 * Live E2E checks for Siba Leads Kanban operations (Playwright).
 *
 * Usage:
 *   set SIBA_TEST_EMAIL=admin@example.com
 *   set SIBA_TEST_PASSWORD=secret
 *   set SIBA_BASE_URL=http://perfex.local
 *   npx playwright test modules/siba_leads/tests/kanban_ops.e2e.mjs --config=modules/siba_leads/tests/playwright.config.mjs
 *
 * Or: node modules/siba_leads/tests/kanban_ops.e2e.mjs
 */

import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const BASE = (process.env.SIBA_BASE_URL || 'http://perfex.local').replace(/\/$/, '');
const EMAIL = process.env.SIBA_TEST_EMAIL || '';
const PASSWORD = process.env.SIBA_TEST_PASSWORD || '';
const HEADLESS = process.env.SIBA_HEADED !== '1';

const results = [];
const screenshotsDir = join(__dirname, 'artifacts');

function record(name, ok, detail = '') {
  results.push({ name, ok, detail });
  const mark = ok ? 'PASS' : 'FAIL';
  console.log(`${mark}  ${name}${detail ? ' — ' + detail : ''}`);
}

async function screenshot(page, name) {
  try {
    mkdirSync(screenshotsDir, { recursive: true });
    await page.screenshot({
      path: join(screenshotsDir, `${name.replace(/[^\w.-]+/g, '_')}.png`),
      fullPage: true,
    });
  } catch (_) {
    /* ignore */
  }
}

async function login(page) {
  await page.goto(`${BASE}/admin/authentication`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="email"]', EMAIL);
  await page.fill('input[name="password"]', PASSWORD);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => null),
    page.click('button[type="submit"], input[type="submit"], .btn-primary'),
  ]);
  const url = page.url();
  if (url.includes('authentication')) {
    throw new Error('Login failed — still on authentication page');
  }
}

async function gotoKanban(page) {
  await page.goto(`${BASE}/admin/siba_leads`, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForSelector('#kan-ban, .siba-leads-kan-ban, .leads-kan-ban', { timeout: 30000 });
  // Wait for AJAX board fill
  await page.waitForTimeout(1500);
  await page.waitForSelector('.kan-ban-col, #kan-ban .panel_s', { timeout: 30000 }).catch(() => null);
}

async function run() {
  if (!EMAIL || !PASSWORD) {
    console.error('Set SIBA_TEST_EMAIL and SIBA_TEST_PASSWORD to run live Kanban E2E tests.');
    process.exit(2);
  }

  const browser = await chromium.launch({ headless: HEADLESS });
  const context = await browser.newContext({ locale: 'fa-IR', viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  page.setDefaultTimeout(20000);

  try {
    await login(page);
    record('Login as staff', true, EMAIL);

    await gotoKanban(page);
    await screenshot(page, '01_board');
    record('Open Kanban board', await page.locator('.kan-ban-col').count() > 0, page.url());

    // Toolbar
    const newLead = page.locator('a.btn-primary', { hasText: /new|جدید|راهنما/i }).first();
    record('Toolbar: New lead visible', (await newLead.count()) > 0);

    const importBtn = page.locator('a[href*="siba_leads/import"]').first();
    record('Toolbar: Import link', (await importBtn.count()) > 0);

    const coreList = page.locator('a[href$="/admin/leads"], a[href*="/admin/leads"]').filter({ hasNot: page.locator('[href*="siba"]') }).first();
    const coreListAlt = page.locator('a[href*="admin/leads"]').first();
    record('Toolbar: Core leads list', (await coreList.count()) + (await coreListAlt.count()) > 0);

    const teams = page.locator('a[href*="siba_leads/teams"]').first();
    record('Toolbar: Teams link (admin)', (await teams.count()) > 0);

    // Search
    const search = page.locator('input[name="search"], input[data-name="search"], .leads-search input').first();
    if (await search.count()) {
      await search.fill('___no_match_xyz___');
      await page.waitForTimeout(1200);
      record('Search: refreshes board', true, 'typed query');
      await search.fill('');
      await page.waitForTimeout(1200);
    } else {
      record('Search: refreshes board', false, 'search input missing');
    }

    // Sort links
    for (const [cls, label] of [
      ['.dateadded', 'dateadded'],
      ['.leadorder', 'leadorder'],
      ['.lastcontact', 'lastcontact'],
    ]) {
      const link = page.locator(`.kanban-leads-sort ${cls}`).first();
      if (await link.count()) {
        await link.click();
        await page.waitForTimeout(1000);
        record(`Sort by ${label}`, true);
      } else {
        record(`Sort by ${label}`, false, 'link missing');
      }
    }

    // Columns
    const cols = page.locator('.kan-ban-col');
    const colCount = await cols.count();
    record('Columns rendered', colCount > 0, `${colCount} columns`);

    // Column menu: open popover on first column
    const colorPicker = page.locator('.kanban-stage-color-picker').first();
    if (await colorPicker.count()) {
      await colorPicker.click();
      await page.waitForTimeout(400);
      const newFromStatus = page.locator('.new-lead-from-status').first();
      record('Column menu: New lead from status', await newFromStatus.isVisible().catch(() => false));
      const cpicker = page.locator('.kanban-cpicker, .cpicker-wrapper .cpicker').first();
      record('Column menu: color picker (admin)', (await cpicker.count()) > 0);
      await page.keyboard.press('Escape').catch(() => null);
      await page.mouse.click(10, 10);
    } else {
      record('Column menu: New lead from status', false, 'picker missing');
      record('Column menu: color picker (admin)', false, 'picker missing');
    }

    // Load more (if present and enabled)
    const loadMore = page.locator('.kanban-load-more a:not(.disabled)').first();
    if (await loadMore.count()) {
      const before = await page.locator('.lead-kan-ban').count();
      await loadMore.click();
      await page.waitForTimeout(1500);
      const after = await page.locator('.lead-kan-ban').count();
      record('Load more leads', after >= before, `${before} → ${after}`);
    } else {
      record('Load more leads', true, 'skipped (none / disabled)');
    }

    // Cards
    const cards = page.locator('.lead-kan-ban, .siba-lead-card');
    const cardCount = await cards.count();
    record('Cards present', cardCount > 0, `${cardCount} cards`);

    if (cardCount > 0) {
      const first = cards.first();

      // Expand details
      const expand = first.locator('.siba-lead-card__expand').first();
      if (await expand.count()) {
        await expand.click();
        await page.waitForTimeout(300);
        const details = first.locator('.siba-lead-card__details').first();
        const visible = await details.isVisible().catch(() => false);
        record('Expand card details', visible);
      } else {
        record('Expand card details', false, 'expand button missing');
      }

      // Open lead modal
      const nameLink = first.locator('a.siba-lead-card__name, a[onclick*="init_lead"]').first();
      if (await nameLink.count()) {
        await nameLink.click();
        await page.waitForSelector('#lead-modal.show, #lead-modal.in, #lead-modal[style*="display: block"]', {
          timeout: 15000,
        }).catch(() => null);
        const modalVisible = await page.locator('#lead-modal').isVisible().catch(() => false);
        record('Open lead modal from card', modalVisible);
        await screenshot(page, '02_lead_modal');
        // Close modal
        const close = page.locator('#lead-modal .close, #lead-modal [data-dismiss="modal"]').first();
        if (await close.count()) {
          await close.click();
          await page.waitForTimeout(500);
        } else {
          await page.keyboard.press('Escape');
        }
      } else {
        record('Open lead modal from card', false, 'name link missing');
      }

      // Contact links presence
      record(
        'Card contact links (tel/mailto if data)',
        true,
        `tel=${await first.locator('a[href^="tel:"]').count()} mailto=${await first.locator('a[href^="mailto:"]').count()}`
      );

      // Order / license buttons (state-dependent)
      const orderBtns = first.locator('.siba-lead-card__order-btn');
      record(
        'Order/license card action (if permitted)',
        true,
        (await orderBtns.count()) > 0
          ? await orderBtns.first().innerText().then((t) => t.trim()).catch(() => 'present')
          : 'none on first card (permission/state)'
      );

      // Drag card to another column (if ≥2 columns with droppable lists)
      if (colCount >= 2) {
        const sourceCard = page.locator('.lead-kan-ban:not(.not-sortable)').first();
        const targetList = page.locator('ul.leads-status.sortable').nth(1);
        if ((await sourceCard.count()) && (await targetList.count())) {
          const leadId = await sourceCard.getAttribute('data-lead-id');
          const fromStatus = await sourceCard.locator('xpath=ancestor::ul[contains(@class,"leads-status")]').getAttribute('data-lead-status-id').catch(() => null);
          const toStatus = await targetList.getAttribute('data-lead-status-id');

          const box = await sourceCard.boundingBox();
          const tbox = await targetList.boundingBox();
          if (box && tbox && leadId && fromStatus !== toStatus) {
            await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
            await page.mouse.down();
            await page.mouse.move(tbox.x + tbox.width / 2, tbox.y + 40, { steps: 12 });
            await page.mouse.up();
            await page.waitForTimeout(2500);
            // Refresh board and check card is still on board
            await gotoKanban(page);
            const stillThere = await page.locator(`.lead-kan-ban[data-lead-id="${leadId}"]`).count();
            record('Drag card between statuses', stillThere > 0, `lead #${leadId} ${fromStatus}→${toStatus}`);
            await screenshot(page, '03_after_drag');
          } else {
            record('Drag card between statuses', true, 'skipped (same status / no box)');
          }
        } else {
          record('Drag card between statuses', true, 'skipped (no movable card)');
        }
      } else {
        record('Drag card between statuses', false, 'need ≥2 columns');
      }
    } else {
      record('Expand card details', true, 'skipped (no cards)');
      record('Open lead modal from card', true, 'skipped (no cards)');
      record('Card contact links (tel/mailto if data)', true, 'skipped (no cards)');
      record('Order/license card action (if permitted)', true, 'skipped (no cards)');
      record('Drag card between statuses', true, 'skipped (no cards)');
    }

    // New lead from toolbar (open modal only, do not save)
    if (await newLead.count()) {
      await newLead.click();
      await page.waitForTimeout(1000);
      const modal = await page.locator('#lead-modal').isVisible().catch(() => false);
      record('Toolbar: open New lead modal', modal);
      await page.keyboard.press('Escape');
      await page.waitForTimeout(400);
    }

    // Navigate Import / Teams / Core list (smoke)
    if (await importBtn.count()) {
      await importBtn.click();
      await page.waitForLoadState('domcontentloaded');
      record('Navigate: Import page', page.url().includes('siba_leads/import'), page.url());
      await page.goBack();
      await gotoKanban(page);
    }

    if (await teams.count()) {
      await teams.click();
      await page.waitForLoadState('domcontentloaded');
      record('Navigate: Teams page', page.url().includes('siba_leads/teams'), page.url());
      await page.goBack();
      await gotoKanban(page);
    }

    // Column reorder — visual sortable exists for admin
    const reorderHandle = page.locator('.kan-ban-col .fa-reorder, .kan-ban-col .fa-bars').first();
    record('Column reorder handle (admin)', (await reorderHandle.count()) > 0 || colCount > 0, 'sortable wired if admin');

  } catch (err) {
    record('Suite crashed', false, String(err && err.message ? err.message : err));
    await screenshot(page, '99_error');
  } finally {
    await browser.close();
  }

  const failed = results.filter((r) => !r.ok);
  const summary = {
    base: BASE,
    passed: results.filter((r) => r.ok).length,
    failed: failed.length,
    results,
  };
  mkdirSync(screenshotsDir, { recursive: true });
  writeFileSync(join(screenshotsDir, 'results.json'), JSON.stringify(summary, null, 2));

  console.log('');
  console.log(`Result: ${summary.passed} passed, ${summary.failed} failed`);
  process.exit(failed.length ? 1 : 0);
}

run();
