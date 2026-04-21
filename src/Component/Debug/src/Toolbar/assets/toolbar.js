(function() {
    var root = document.getElementById('wppack-debug');
    if (!root) return;

    var STORAGE_KEY = 'wppack-debug-minimized';
    if (localStorage.getItem(STORAGE_KEY) === '1') {
        root.classList.add('wpd-minimized');
    }

    // Wrap non-kv tables for horizontal scroll
    root.querySelectorAll('.wpd-table:not(.wpd-table-kv)').forEach(function(table) {
        var wrap = document.createElement('div');
        wrap.className = 'wpd-table-wrap';
        table.parentNode.insertBefore(wrap, table);
        wrap.appendChild(table);
    });

    // Sticky thead simulation for wrapped tables
    var contentBody = root.querySelector('.wpd-content-body');
    if (contentBody) {
        function updateStickyTheads() {
            var bodyRect = contentBody.getBoundingClientRect();
            var stickyTop = bodyRect.top;
            root.querySelectorAll('.wpd-table-wrap thead').forEach(function(thead) {
                var table = thead.closest('table');
                // Reset to measure natural position
                thead.style.transform = '';
                var theadRect = thead.getBoundingClientRect();
                var tableRect = table.getBoundingClientRect();
                var theadHeight = theadRect.height;
                // Stick when thead scrolls above content body top,
                // stop when table bottom reaches thead bottom
                if (theadRect.top < stickyTop && tableRect.bottom > stickyTop + theadHeight) {
                    thead.style.transform = 'translateY(' + (stickyTop - theadRect.top - 1) + 'px)';
                }
            });
        }
        contentBody.addEventListener('scroll', updateStickyTheads);
    }

    var overlay = root.querySelector('.wpd-overlay');
    var contentHeader = root.querySelector('.wpd-content-header .wpd-panel-title');
    var activePanel = null;

    // Indicator scroll fade
    var indicatorsWrap = root.querySelector('.wpd-bar-indicators-wrap');
    var indicatorsScroll = root.querySelector('.wpd-bar-indicators');
    function updateIndicatorFade() {
        if (!indicatorsWrap || !indicatorsScroll) return;
        var sl = indicatorsScroll.scrollLeft;
        var maxSl = indicatorsScroll.scrollWidth - indicatorsScroll.clientWidth;
        if (maxSl <= 0) {
            indicatorsWrap.classList.remove('wpd-fade-left', 'wpd-fade-right');
            return;
        }
        indicatorsWrap.classList.toggle('wpd-fade-left', sl > 2);
        indicatorsWrap.classList.toggle('wpd-fade-right', sl < maxSl - 2);
    }
    if (indicatorsScroll) {
        indicatorsScroll.addEventListener('scroll', updateIndicatorFade);
        updateIndicatorFade();
    }

    function closeOverlay() {
        overlay.style.display = 'none';
        root.classList.remove('wpd-panel-open');
        var indicators = root.querySelectorAll('.wpd-indicator');
        for (var i = 0; i < indicators.length; i++) {
            indicators[i].classList.remove('wpd-active');
        }
        var items = root.querySelectorAll('.wpd-sidebar-item');
        for (var i = 0; i < items.length; i++) {
            items[i].classList.remove('wpd-active');
        }
        var wpBtn = root.querySelector('.wpd-bar-wp');
        if (wpBtn) wpBtn.classList.remove('wpd-active');
        activePanel = null;
        resetPluginDetailView();
    }

    function resetPluginDetailView() {
        var lists = root.querySelectorAll('.wpd-plugin-list');
        for (var i = 0; i < lists.length; i++) {
            lists[i].style.display = '';
        }
        var details = root.querySelectorAll('.wpd-plugin-detail');
        for (var i = 0; i < details.length; i++) {
            details[i].style.display = 'none';
        }
    }

    function openPanel(name) {
        // Show overlay
        overlay.style.display = 'flex';
        root.classList.add('wpd-panel-open');

        // Switch content
        var contents = root.querySelectorAll('.wpd-panel-content');
        for (var i = 0; i < contents.length; i++) {
            contents[i].style.display = 'none';
        }
        var target = root.querySelector('#wpd-pc-' + name);
        if (target) target.style.display = '';

        // Scroll content to top
        var body = root.querySelector('.wpd-content-body');
        if (body) body.scrollTop = 0;

        // Update header title from sidebar label
        var sidebarItem = root.querySelector('.wpd-sidebar-item[data-panel="' + name + '"]');
        if (sidebarItem && contentHeader) {
            var label = sidebarItem.querySelector('.wpd-sidebar-label');
            contentHeader.textContent = label ? label.textContent : name;
        }

        // Highlight sidebar item
        var items = root.querySelectorAll('.wpd-sidebar-item');
        for (var i = 0; i < items.length; i++) {
            if (items[i].getAttribute('data-panel') === name) {
                items[i].classList.add('wpd-active');
            } else {
                items[i].classList.remove('wpd-active');
            }
        }

        // Highlight indicator
        var indicators = root.querySelectorAll('.wpd-indicator');
        for (var i = 0; i < indicators.length; i++) {
            if (indicators[i].getAttribute('data-panel') === name) {
                indicators[i].classList.add('wpd-active');
                var accent = indicators[i].getAttribute('data-accent');
                indicators[i].style.setProperty('--wpd-accent', accent || 'var(--wpd-primary)');
            } else {
                indicators[i].classList.remove('wpd-active');
            }
        }

        // Highlight logo/version button for wordpress panel
        var wpBtn = root.querySelector('.wpd-bar-wp');
        if (wpBtn) {
            if (name === 'wordpress') {
                wpBtn.classList.add('wpd-active');
            } else {
                wpBtn.classList.remove('wpd-active');
            }
        }

        activePanel = name;
        resetPluginDetailView();
    }

    root.addEventListener('click', function(e) {
        // Mini button — restore toolbar
        var miniBtn = e.target.closest('.wpd-mini');
        if (miniBtn) {
            root.classList.remove('wpd-minimized');
            localStorage.removeItem(STORAGE_KEY);
            return;
        }

        // Logo/version click — toggle WordPress panel
        var wpBtn = e.target.closest('.wpd-bar-wp');
        if (wpBtn) {
            var panel = wpBtn.getAttribute('data-panel');
            if (activePanel === panel) {
                closeOverlay();
            } else {
                openPanel(panel);
            }
            return;
        }

        // Sidebar item click — always switch, never close
        var sidebarItem = e.target.closest('.wpd-sidebar-item');
        if (sidebarItem) {
            var panel = sidebarItem.getAttribute('data-panel');
            if (activePanel !== panel) {
                openPanel(panel);
            }
            return;
        }

        // Indicator click — toggle panel
        var indicator = e.target.closest('.wpd-indicator');
        if (indicator) {
            var panel = indicator.getAttribute('data-panel');
            if (activePanel === panel) {
                closeOverlay();
            } else {
                openPanel(panel);
            }
            return;
        }

        // Environment info click — toggle panel
        var envBar = e.target.closest('.wpd-bar-env');
        if (envBar) {
            var panel = envBar.getAttribute('data-panel');
            if (panel) {
                if (activePanel === panel) {
                    closeOverlay();
                } else {
                    openPanel(panel);
                }
            }
            return;
        }

        // Close button in content header
        var closeBtn = e.target.closest('[data-action="close-panel"]');
        if (closeBtn) {
            closeOverlay();
            return;
        }

        // Plugin detail link
        var pluginLink = e.target.closest('.wpd-plugin-detail-link');
        if (pluginLink) {
            var pluginSlug = pluginLink.getAttribute('data-plugin');
            var panelContent = pluginLink.closest('.wpd-panel-content');
            if (panelContent) {
                var list = panelContent.querySelector('.wpd-plugin-list');
                if (list) list.style.display = 'none';
                var detail = panelContent.querySelector('.wpd-plugin-detail[data-plugin="' + pluginSlug + '"]');
                if (detail) detail.style.display = '';
            }
            return;
        }

        // Plugin back button
        var backBtn = e.target.closest('[data-action="plugin-back"]');
        if (backBtn) {
            var panelContent = backBtn.closest('.wpd-panel-content');
            if (panelContent) {
                var details = panelContent.querySelectorAll('.wpd-plugin-detail');
                for (var i = 0; i < details.length; i++) {
                    details[i].style.display = 'none';
                }
                var list = panelContent.querySelector('.wpd-plugin-list');
                if (list) list.style.display = '';
            }
            return;
        }

        // Close/minimize toolbar
        var minimizeBtn = e.target.closest('[data-action="minimize"]');
        if (minimizeBtn) {
            closeOverlay();
            root.classList.add('wpd-minimized');
            localStorage.setItem(STORAGE_KEY, '1');
        }
    });

    // Log filter tabs
    root.addEventListener('click', function(e) {
        var tab = e.target.closest('.wpd-log-tab');
        if (tab) {
            var tabs = tab.closest('.wpd-log-tabs');
            tabs.querySelectorAll('.wpd-log-tab').forEach(function(t) { t.classList.remove('wpd-active'); });
            tab.classList.add('wpd-active');
            var filter = tab.getAttribute('data-log-filter');
            var section = tabs.closest('.wpd-section');
            section.querySelectorAll('tr[data-log-level]').forEach(function(row) {
                var level = row.getAttribute('data-log-level');
                // Always hide context rows on filter change
                if (row.classList.contains('wpd-log-context')) {
                    row.style.display = 'none';
                    return;
                }
                var show = false;
                if (filter === 'all') { show = true; }
                else if (filter === 'error') { show = (['emergency','alert','critical','error'].indexOf(level) !== -1); }
                else if (filter === 'deprecation') { show = level === 'deprecation'; }
                else if (filter === 'warning') { show = level === 'warning'; }
                else if (filter === 'notice') { show = level === 'notice'; }
                else if (filter === 'info') { show = level === 'info'; }
                else if (filter === 'debug') { show = level === 'debug'; }
                row.style.display = show ? '' : 'none';
                // Reset indicator to +
                var indicator = row.querySelector('.wpd-log-indicator');
                if (indicator) indicator.textContent = '+';
            });
            return;
        }
        // Context toggle
        var toggle = e.target.closest('.wpd-log-toggle');
        if (toggle) {
            var ctx = toggle.nextElementSibling;
            if (ctx && ctx.classList.contains('wpd-log-context')) {
                var opening = ctx.style.display === 'none';
                ctx.style.display = opening ? '' : 'none';
                var indicator = toggle.querySelector('.wpd-log-indicator');
                if (indicator) indicator.textContent = opening ? '\u2212' : '+';
            }
        }
    });

    // Escape key closes overlay
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && activePanel !== null) {
            closeOverlay();
        }
    });

    // Click outside toolbar closes overlay
    document.addEventListener('click', function(e) {
        if (activePanel !== null && !root.contains(e.target)) {
            e.stopPropagation();
            e.preventDefault();
            closeOverlay();
        }
    }, true);

    // Timeline bar tooltips
    var tooltip = document.createElement('div');
    tooltip.className = 'wpd-tooltip';
    tooltip.style.display = 'none';
    root.appendChild(tooltip);

    root.addEventListener('mouseover', function(e) {
        var el = e.target.closest('[data-tooltip]');
        if (!el) return;
        tooltip.textContent = el.getAttribute('data-tooltip');
        tooltip.style.display = '';
        var rect = el.getBoundingClientRect();
        var tipRect = tooltip.getBoundingClientRect();
        var left = rect.left + rect.width / 2 - tipRect.width / 2;
        if (left < 4) left = 4;
        if (left + tipRect.width > window.innerWidth - 4) left = window.innerWidth - 4 - tipRect.width;
        tooltip.style.left = left + 'px';
        tooltip.style.top = (rect.top - tipRect.height - 6) + 'px';
    });

    root.addEventListener('mouseout', function(e) {
        var el = e.target.closest('[data-tooltip]');
        if (el) tooltip.style.display = 'none';
    });
})();

// --- Ajax request tracking ---
(function(){
    var ajaxCount = 0;
    function isAdminAjax(url){
        try { return new URL(url, location.origin).pathname.indexOf('admin-ajax.php') !== -1; } catch(e){ return false; }
    }
    function extractAction(url, body){
        try {
            var u = new URL(url, location.origin);
            var a = u.searchParams.get('action');
            if(a) return a;
        } catch(e){}
        if(body){
            if(typeof body === 'string'){
                try { var p = new URLSearchParams(body); var a2 = p.get('action'); if(a2) return a2; } catch(e){}
            }
            if(typeof FormData !== 'undefined' && body instanceof FormData){
                var a3 = body.get('action'); if(a3) return String(a3);
            }
        }
        return '(unknown)';
    }
    function addAjaxRow(action, method, status, duration, size){
        var tbody = document.getElementById('wpd-ajax-tbody');
        var empty = document.getElementById('wpd-ajax-empty');
        if(!tbody) return;
        if(empty) empty.style.display = 'none';
        ajaxCount++;
        var tr = document.createElement('tr');
        var statusColor = status >= 200 && status < 300 ? 'var(--wpd-green)' : (status >= 400 ? 'var(--wpd-red)' : 'var(--wpd-yellow)');
        var mc = {GET:'green',POST:'primary',PUT:'yellow',PATCH:'yellow',DELETE:'red'}[method] || 'gray';
        tr.innerHTML = '<td><code>' + action + '</code></td>'
            + '<td><span class="wpd-badge wpd-badge-' + mc + '">' + method + '</span></td>'
            + '<td><span style="color:' + statusColor + '">' + status + '</span></td>'
            + '<td class="wpd-col-right">' + duration.toFixed(0) + ' ms</td>'
            + '<td class="wpd-col-right">' + (size > 0 ? (size > 1024 ? (size/1024).toFixed(1) + ' KB' : size + ' B') : '-') + '</td>';
        tbody.appendChild(tr);
        // Update indicator
        var indicators = document.querySelectorAll('.wpd-indicator[data-panel="ajax"] .wpd-indicator-value');
        indicators.forEach(function(b){ b.textContent = String(ajaxCount); });
    }

    // Patch XMLHttpRequest
    var origOpen = XMLHttpRequest.prototype.open;
    var origSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function(method, url){
        this._wpdMethod = method;
        this._wpdUrl = String(url);
        return origOpen.apply(this, arguments);
    };
    XMLHttpRequest.prototype.send = function(body){
        if(this._wpdUrl && isAdminAjax(this._wpdUrl)){
            var self = this;
            var action = extractAction(self._wpdUrl, body);
            var method = (self._wpdMethod || 'GET').toUpperCase();
            var start = performance.now();
            self.addEventListener('loadend', function(){
                var dur = performance.now() - start;
                var size = 0;
                try { var r = self.responseText; if(r) size = r.length; } catch(e){}
                addAjaxRow(action, method, self.status, dur, size);
            });
        }
        return origSend.apply(this, arguments);
    };

    // Patch fetch
    var origFetch = window.fetch;
    if(origFetch){
        window.fetch = function(input, init){
            var url = typeof input === 'string' ? input : (input && input.url ? input.url : '');
            if(!isAdminAjax(url)) return origFetch.apply(this, arguments);
            var method = ((init && init.method) || 'GET').toUpperCase();
            var body = (init && init.body) || null;
            var action = extractAction(url, body);
            var start = performance.now();
            return origFetch.apply(this, arguments).then(function(response){
                var dur = performance.now() - start;
                var clone = response.clone();
                clone.text().then(function(text){
                    addAjaxRow(action, method, response.status, dur, text.length);
                }).catch(function(){
                    addAjaxRow(action, method, response.status, dur, 0);
                });
                return response;
            }).catch(function(err){
                addAjaxRow(action, method, 0, performance.now() - start, 0);
                throw err;
            });
        };
    }
})();
