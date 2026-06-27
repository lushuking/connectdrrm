/**
 * Real-time table refresh orchestrator.
 *
 * Strategy:
 * - Use existing Notifications SSE as a lightweight "change bus".
 * - When a notification update arrives, dispatch `realtime:update` and try to refresh
 *   whichever page/table is currently active (without hard coupling to page internals).
 * - Provide a gentle polling fallback to keep tables fresh even when no notification is emitted.
 */
(function () {
  const STATE = {
    scheduled: false,
    lastRefreshAt: 0,
    // Don’t hammer endpoints if multiple events arrive at once
    minGapMs: 1200,
    // Soft fallback refresh (covers cases where no notification is created)
    fallbackIntervalMs: 10000,
  };

  function safeCall(fn, ...args) {
    try {
      if (typeof fn === 'function') return fn(...args);
    } catch (e) {
      // Keep quiet; refresh is best-effort
      console.debug('[realtime-tables] refresh call failed', e);
    }
  }

  function scheduleRefresh(reason) {
    const now = Date.now();
    if (STATE.scheduled) return;
    if (now - STATE.lastRefreshAt < STATE.minGapMs) return;

    STATE.scheduled = true;
    // Small delay to coalesce bursts
    setTimeout(() => {
      STATE.scheduled = false;
      STATE.lastRefreshAt = Date.now();
      refreshVisibleTables(reason || 'scheduled');
    }, 250);
  }

  function refreshVisibleTables(reason) {
    if (document.hidden) return;

    // Requests page (municipality + pdrrmo) already uses a refresh event bus
    if (typeof window.triggerRequestDataRefresh === 'function') {
      safeCall(window.triggerRequestDataRefresh);
    } else {
      // Fallback: dispatch event in case inline scripts listen to it
      try {
        document.dispatchEvent(new CustomEvent('requests:refresh', { detail: { reason } }));
      } catch (_) {}
    }

    // Approving authority approvals page has global `loadPendingApprovals`
    safeCall(window.loadPendingApprovals);

    // PDRRMO monitor requests page has global `loadRequests`
    // (note: name overlaps with other pages sometimes, but the table IDs act as guard rails)
    const hasMonitorRequestsTable = !!document.getElementById('requestsTableBody');
    if (hasMonitorRequestsTable) {
      safeCall(window.loadRequests);
      safeCall(window.refreshRequests);
    }

    // Resources page (overview/detail) is class-based, refresh only if present
    if (window.resourcesPage) {
      // Overview card grid is derived from static data; keep it in sync when possible
      if (typeof window.resourcesPage.refreshMunicipalitiesOverview === 'function') {
        safeCall(window.resourcesPage.refreshMunicipalitiesOverview.bind(window.resourcesPage));
      }

      // If user is currently viewing a municipality detail, refresh from API
      const detailEl = document.getElementById('resourcesDetail');
      const inDetailView = detailEl && detailEl.style.display !== 'none';
      if (inDetailView && window.resourcesPage.currentMunicipalityId) {
        if (typeof window.resourcesPage.refreshMunicipalityResourcesFromAPI === 'function') {
          safeCall(
            window.resourcesPage.refreshMunicipalityResourcesFromAPI.bind(window.resourcesPage),
            window.resourcesPage.currentMunicipalityId
          );
        }
      }
    }

    // Hazard / dashboard / reports: best-effort event hooks for pages that want it
    try {
      document.dispatchEvent(new CustomEvent('realtime:refresh', { detail: { reason } }));
    } catch (_) {}
  }

  // Listen to the bus (emitted by header notifications SSE)
  document.addEventListener('realtime:update', (e) => {
    const reason = (e && e.detail && e.detail.reason) ? e.detail.reason : 'sse';
    scheduleRefresh(reason);
  });

  // Fallback polling: refresh gently in the background
  setInterval(() => {
    scheduleRefresh('poll');
  }, STATE.fallbackIntervalMs);

  // Expose a manual helper (useful for debugging)
  window.refreshRealtimeTablesNow = function () {
    STATE.lastRefreshAt = 0;
    scheduleRefresh('manual');
  };
})();

