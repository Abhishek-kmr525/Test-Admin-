/**
 * Admin Panel JavaScript Functions
 */

// Initialize admin panel
document.addEventListener('DOMContentLoaded', function() {
    initializeAdminPanel();
});

function initializeAdminPanel() {
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize data tables
    initializeDataTables();
    
    // Initialize charts if Chart.js is available
    if (typeof Chart !== 'undefined') {
        initializeCharts();
    }
    
    // Auto-refresh data every 5 minutes
    setInterval(refreshDashboardData, 300000);
    
    // Initialize keyboard shortcuts
    initializeKeyboardShortcuts();
}

// Tooltip initialization
function initializeTooltips() {
    const tooltipElements = document.querySelectorAll('[title]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const tooltip = document.createElement('div');
    tooltip.className = 'admin-tooltip';
    tooltip.textContent = e.target.getAttribute('title');
    tooltip.style.position = 'absolute';
    tooltip.style.backgroundColor = '#333';
    tooltip.style.color = 'white';
    tooltip.style.padding = '8px 12px';
    tooltip.style.borderRadius = '4px';
    tooltip.style.fontSize = '12px';
    tooltip.style.zIndex = '1000';
    tooltip.style.pointerEvents = 'none';
    
    // Remove title to prevent browser tooltip
    e.target.setAttribute('data-original-title', e.target.getAttribute('title'));
    e.target.removeAttribute('title');
    
    document.body.appendChild(tooltip);
    
    // Position tooltip
    const rect = e.target.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
    
    e.target.tooltipElement = tooltip;
}

function hideTooltip(e) {
    if (e.target.tooltipElement) {
        document.body.removeChild(e.target.tooltipElement);
        e.target.tooltipElement = null;
    }
    
    // Restore original title
    if (e.target.getAttribute('data-original-title')) {
        e.target.setAttribute('title', e.target.getAttribute('data-original-title'));
        e.target.removeAttribute('data-original-title');
    }
}

// Data table enhancements
function initializeDataTables() {
    const tables = document.querySelectorAll('.data-table');
    tables.forEach(table => {
        // Add sorting capability
        addTableSorting(table);
        
        // Add row highlighting
        addRowHighlighting(table);
    });
}

function addTableSorting(table) {
    const headers = table.querySelectorAll('th');
    headers.forEach((header, index) => {
        if (!header.classList.contains('no-sort')) {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => sortTable(table, index));
            
            // Add sort indicator
            const sortIcon = document.createElement('i');
            sortIcon.className = 'fas fa-sort sort-icon';
            sortIcon.style.marginLeft = '5px';
            sortIcon.style.opacity = '0.5';
            header.appendChild(sortIcon);
        }
    });
}

function sortTable(table, columnIndex) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    // Determine sort direction
    const header = table.querySelectorAll('th')[columnIndex];
    const isAscending = !header.classList.contains('sort-desc');
    
    // Clear all sort classes
    table.querySelectorAll('th').forEach(h => {
        h.classList.remove('sort-asc', 'sort-desc');
        const icon = h.querySelector('.sort-icon');
        if (icon) {
            icon.className = 'fas fa-sort sort-icon';
        }
    });
    
    // Set sort class and icon
    header.classList.add(isAscending ? 'sort-asc' : 'sort-desc');
    const icon = header.querySelector('.sort-icon');
    if (icon) {
        icon.className = `fas fa-sort-${isAscending ? 'up' : 'down'} sort-icon`;
    }
    
    // Sort rows
    rows.sort((a, b) => {
        const aValue = a.cells[columnIndex].textContent.trim();
        const bValue = b.cells[columnIndex].textContent.trim();
        
        // Check if values are numbers
        const aNum = parseFloat(aValue);
        const bNum = parseFloat(bValue);
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return isAscending ? aNum - bNum : bNum - aNum;
        }
        
        // Check if values are dates
        const aDate = new Date(aValue);
        const bDate = new Date(bValue);
        
        if (!isNaN(aDate) && !isNaN(bDate)) {
            return isAscending ? aDate - bDate : bDate - aDate;
        }
        
        // String comparison
        return isAscending ? 
            aValue.localeCompare(bValue) : 
            bValue.localeCompare(aValue);
    });
    
    // Reorder rows in DOM
    rows.forEach(row => tbody.appendChild(row));
}

function addRowHighlighting(table) {
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f0f8ff';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
}

// Chart initialization
function initializeCharts() {
    // User registration chart
    const userChartCanvas = document.getElementById('userRegistrationChart');
    if (userChartCanvas) {
        createUserRegistrationChart(userChartCanvas);
    }
    
    // Revenue chart
    const revenueChartCanvas = document.getElementById('revenueChart');
    if (revenueChartCanvas) {
        createRevenueChart(revenueChartCanvas);
    }
    
    // Activity chart
    const activityChartCanvas = document.getElementById('activityChart');
    if (activityChartCanvas) {
        createActivityChart(activityChartCanvas);
    }
}

function createUserRegistrationChart(canvas) {
    fetch('ajax/chart_data.php?type=user_registration')
        .then(response => response.json())
        .then(data => {
            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'New Users',
                        data: data.values,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'User Registrations (Last 30 Days)'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
}

function createRevenueChart(canvas) {
    fetch('ajax/chart_data.php?type=revenue')
        .then(response => response.json())
        .then(data => {
            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Revenue ($)',
                        data: data.values,
                        backgroundColor: 'rgba(46, 125, 50, 0.8)',
                        borderColor: 'rgba(46, 125, 50, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Monthly Revenue'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        });
}

function createActivityChart(canvas) {
    fetch('ajax/chart_data.php?type=activity')
        .then(response => response.json())
        .then(data => {
            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: [
                            '#FF6384',
                            '#36A2EB',
                            '#FFCE56',
                            '#4BC0C0',
                            '#9966FF'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'User Activity Distribution'
                        },
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        });
}

// Dashboard data refresh
function refreshDashboardData() {
    if (window.location.pathname.includes('index.php')) {
        fetch('ajax/dashboard_stats.php')
            .then(response => response.json())
            .then(data => {
                updateDashboardStats(data);
            })
            .catch(error => {
                console.error('Failed to refresh dashboard data:', error);
            });
    }
}

function updateDashboardStats(data) {
    // Update stat cards
    Object.keys(data).forEach(key => {
        const element = document.querySelector(`[data-stat="${key}"]`);
        if (element) {
            element.textContent = data[key];
            
            // Add animation
            element.classList.add('stat-updated');
            setTimeout(() => {
                element.classList.remove('stat-updated');
            }, 500);
        }
    });
}

// Keyboard shortcuts
function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + / for help
        if ((e.ctrlKey || e.metaKey) && e.key === '/') {
            e.preventDefault();
            showKeyboardShortcuts();
        }
        
        // Escape to close modals
        if (e.key === 'Escape') {
            closeAllModals();
        }
        
        // Ctrl/Cmd + S to save forms
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            const activeForm = document.querySelector('form:focus-within');
            if (activeForm) {
                e.preventDefault();
                const submitBtn = activeForm.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.click();
                }
            }
        }
    });
}

function showKeyboardShortcuts() {
    const shortcuts = [
        { key: 'Ctrl/Cmd + /', description: 'Show keyboard shortcuts' },
        { key: 'Escape', description: 'Close modals' },
        { key: 'Ctrl/Cmd + S', description: 'Save current form' },
        { key: 'Tab', description: 'Navigate between elements' },
        { key: 'Enter', description: 'Submit forms/activate buttons' }
    ];
    
    let shortcutsHtml = '<h3>Keyboard Shortcuts</h3><ul>';
    shortcuts.forEach(shortcut => {
        shortcutsHtml += `<li><strong>${shortcut.key}</strong>: ${shortcut.description}</li>`;
    });
    shortcutsHtml += '</ul>';
    
    showNotification(shortcutsHtml, 'info', 5000);
}

function closeAllModals() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.style.display = 'none';
    });
}

// Notification system
function showNotification(message, type = 'success', duration = 3000) {
    const notification = document.createElement('div');
    notification.className = `admin-notification ${type}`;
    notification.innerHTML = message;
    
    // Style the notification
    Object.assign(notification.style, {
        position: 'fixed',
        top: '20px',
        right: '20px',
        padding: '15px 20px',
        backgroundColor: type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8',
        color: 'white',
        borderRadius: '5px',
        zIndex: '9999',
        minWidth: '300px',
        maxWidth: '500px',
        boxShadow: '0 5px 15px rgba(0,0,0,0.2)',
        transform: 'translateX(400px)',
        transition: 'transform 0.3s ease'
    });
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 10);
    
    // Auto remove
    setTimeout(() => {
        notification.style.transform = 'translateX(400px)';
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, duration);
    
    // Click to dismiss
    notification.addEventListener('click', () => {
        notification.style.transform = 'translateX(400px)';
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 300);
    });
}

// Form validation helpers
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validatePhone(phone) {
    const re = /^\+?[\d\s\-\(\)]+$/;
    return re.test(phone) && phone.replace(/\D/g, '').length >= 10;
}

function validateRequired(value) {
    return value && value.toString().trim().length > 0;
}

// AJAX helpers
function makeRequest(url, options = {}) {
    const defaults = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    const config = Object.assign(defaults, options);
    
    return fetch(url, config)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .catch(error => {
            console.error('Request failed:', error);
            showNotification('Request failed: ' + error.message, 'error');
            throw error;
        });
}

// Export data functionality
function exportTableData(table, filename = 'export.csv') {
    const rows = table.querySelectorAll('tr');
    const csvContent = [];
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('th, td');
        const rowData = [];
        
        cells.forEach(cell => {
            // Clean up cell content
            let content = cell.textContent.trim();
            content = content.replace(/"/g, '""'); // Escape quotes
            if (content.includes(',') || content.includes('"') || content.includes('\n')) {
                content = `"${content}"`;
            }
            rowData.push(content);
        });
        
        csvContent.push(rowData.join(','));
    });
    
    const csvString = csvContent.join('\n');
    const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
    
    const link = document.createElement('a');
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }
}

// Dark mode toggle
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    const isDarkMode = document.body.classList.contains('dark-mode');
    localStorage.setItem('admin_dark_mode', isDarkMode);
    
    // Update dark mode icon
    const darkModeBtn = document.querySelector('.dark-mode-toggle');
    if (darkModeBtn) {
        const icon = darkModeBtn.querySelector('i');
        if (icon) {
            icon.className = isDarkMode ? 'fas fa-sun' : 'fas fa-moon';
        }
    }
}

// Load dark mode preference
function loadDarkModePreference() {
    const isDarkMode = localStorage.getItem('admin_dark_mode') === 'true';
    if (isDarkMode) {
        document.body.classList.add('dark-mode');
    }
}

// Initialize dark mode on load
document.addEventListener('DOMContentLoaded', loadDarkModePreference);

// Print functionality
function printTable(tableSelector) {
    const table = document.querySelector(tableSelector);
    if (!table) return;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Print Table</title>
            <style>
                body { font-family: Arial, sans-serif; }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .action-buttons { display: none; }
                @media print {
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <h1>Data Export</h1>
            ${table.outerHTML}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}

// Auto-save functionality for forms
function enableAutoSave(formSelector, interval = 30000) {
    const form = document.querySelector(formSelector);
    if (!form) return;
    
    let saveTimer;
    const inputs = form.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
        input.addEventListener('input', () => {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(() => {
                autoSaveForm(form);
            }, interval);
        });
    });
}

function autoSaveForm(form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Save to localStorage as backup
    const formId = form.id || 'auto_save_form';
    localStorage.setItem(`admin_autosave_${formId}`, JSON.stringify({
        data: data,
        timestamp: Date.now()
    }));
    
    showNotification('Form auto-saved', 'info', 1000);
}

function loadAutoSavedData(formSelector) {
    const form = document.querySelector(formSelector);
    if (!form) return;
    
    const formId = form.id || 'auto_save_form';
    const savedData = localStorage.getItem(`admin_autosave_${formId}`);
    
    if (savedData) {
        const parsed = JSON.parse(savedData);
        const ageMinutes = (Date.now() - parsed.timestamp) / (1000 * 60);
        
        // Only restore if saved within last 24 hours
        if (ageMinutes < 1440) {
            Object.keys(parsed.data).forEach(key => {
                const input = form.querySelector(`[name="${key}"]`);
                if (input) {
                    if (input.type === 'checkbox') {
                        input.checked = parsed.data[key] === 'on';
                    } else {
                        input.value = parsed.data[key];
                    }
                }
            });
            
            showNotification('Form data restored from auto-save', 'info');
        }
    }
}

// Bulk actions functionality
function initializeBulkActions() {
    const bulkCheckboxes = document.querySelectorAll('.bulk-checkbox');
    const selectAllCheckbox = document.querySelector('.bulk-select-all');
    const bulkActionSelect = document.querySelector('.bulk-action-select');
    const bulkActionBtn = document.querySelector('.bulk-action-btn');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            bulkCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActionButton();
        });
    }
    
    bulkCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActionButton);
    });
    
    if (bulkActionBtn) {
        bulkActionBtn.addEventListener('click', executeBulkAction);
    }
}

function updateBulkActionButton() {
    const checkedBoxes = document.querySelectorAll('.bulk-checkbox:checked');
    const bulkActionBtn = document.querySelector('.bulk-action-btn');
    const bulkCounter = document.querySelector('.bulk-counter');
    
    if (bulkActionBtn) {
        bulkActionBtn.disabled = checkedBoxes.length === 0;
    }
    
    if (bulkCounter) {
        bulkCounter.textContent = `${checkedBoxes.length} selected`;
    }
}

function executeBulkAction() {
    const checkedBoxes = document.querySelectorAll('.bulk-checkbox:checked');
    const actionSelect = document.querySelector('.bulk-action-select');
    
    if (checkedBoxes.length === 0 || !actionSelect) {
        showNotification('Please select items and an action', 'error');
        return;
    }
    
    const action = actionSelect.value;
    const ids = Array.from(checkedBoxes).map(cb => cb.value);
    
    if (!action) {
        showNotification('Please select an action', 'error');
        return;
    }
    
    if (!confirm(`Are you sure you want to ${action} ${ids.length} items?`)) {
        return;
    }
    
    // Execute bulk action via AJAX
    makeRequest('ajax/bulk_actions.php', {
        method: 'POST',
        body: JSON.stringify({
            action: action,
            ids: ids
        })
    })
    .then(response => {
        if (response.success) {
            showNotification(`Bulk action completed: ${response.message}`, 'success');
            location.reload(); // Refresh the page to show changes
        } else {
            showNotification(`Bulk action failed: ${response.error}`, 'error');
        }
    });
}

// Search functionality enhancement
function enhanceSearch() {
    const searchInputs = document.querySelectorAll('.search-input, input[type="search"]');
    
    searchInputs.forEach(input => {
        let searchTimer;
        
        input.addEventListener('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                performSearch(this);
            }, 300); // Debounce search
        });
        
        // Add clear button
        const clearBtn = document.createElement('button');
        clearBtn.innerHTML = '<i class="fas fa-times"></i>';
        clearBtn.className = 'search-clear-btn';
        clearBtn.type = 'button';
        clearBtn.style.cssText = `
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            display: none;
        `;
        
        // Make parent relative if needed
        if (input.parentElement.style.position !== 'relative') {
            input.parentElement.style.position = 'relative';
        }
        
        input.parentElement.appendChild(clearBtn);
        
        clearBtn.addEventListener('click', () => {
            input.value = '';
            input.dispatchEvent(new Event('input'));
            input.focus();
        });
        
        input.addEventListener('input', function() {
            clearBtn.style.display = this.value ? 'block' : 'none';
        });
    });
}

function performSearch(searchInput) {
    const query = searchInput.value.trim();
    const form = searchInput.closest('form');
    
    if (form && (query.length >= 2 || query.length === 0)) {
        // Auto-submit search form
        form.submit();
    }
}

// Real-time updates via WebSocket or Server-Sent Events
function initializeRealTimeUpdates() {
    if (typeof EventSource !== 'undefined') {
        const eventSource = new EventSource('ajax/real_time_updates.php');
        
        eventSource.onmessage = function(event) {
            const data = JSON.parse(event.data);
            handleRealTimeUpdate(data);
        };
        
        eventSource.onerror = function(event) {
            console.error('Real-time updates connection error:', event);
            // Reconnect after 5 seconds
            setTimeout(() => {
                eventSource.close();
                initializeRealTimeUpdates();
            }, 5000);
        };
        
        // Close connection when page is unloaded
        window.addEventListener('beforeunload', () => {
            eventSource.close();
        });
    }
}

function handleRealTimeUpdate(data) {
    switch (data.type) {
        case 'user_registered':
            updateUserCount(data.count);
            showNotification(`New user registered: ${data.email}`, 'info');
            break;
        case 'reminder_sent':
            updateReminderCount(data.count);
            break;
        case 'system_alert':
            showNotification(data.message, 'warning', 10000);
            break;
        default:
            console.log('Unknown real-time update:', data);
    }
}

function updateUserCount(newCount) {
    const userCountElement = document.querySelector('[data-stat="total_users"]');
    if (userCountElement) {
        userCountElement.textContent = newCount;
        userCountElement.classList.add('stat-updated');
        setTimeout(() => {
            userCountElement.classList.remove('stat-updated');
        }, 500);
    }
}

function updateReminderCount(newCount) {
    const reminderCountElement = document.querySelector('[data-stat="total_reminders"]');
    if (reminderCountElement) {
        reminderCountElement.textContent = newCount;
        reminderCountElement.classList.add('stat-updated');
        setTimeout(() => {
            reminderCountElement.classList.remove('stat-updated');
        }, 500);
    }
}

// Modal functionality
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        
        // Focus on first input in modal
        const firstInput = modal.querySelector('input, select, textarea');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Initialize all enhancements when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeBulkActions();
    enhanceSearch();
    initializeRealTimeUpdates();
    
    // Enable auto-save for settings forms
    enableAutoSave('.settings-form');
    loadAutoSavedData('.settings-form');
    
    // Add export buttons to tables
    document.querySelectorAll('.data-table').forEach(table => {
        addExportButton(table);
    });
    
    // Initialize modal close buttons
    document.querySelectorAll('.modal-close').forEach(closeBtn => {
        closeBtn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                closeModal(modal.id);
            }
        });
    });
    
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            closeModal(event.target.id);
        }
    });
});

function addExportButton(table) {
    const container = table.closest('.data-table-container');
    if (!container) return;
    
    const exportBtn = document.createElement('button');
    exportBtn.innerHTML = '<i class="fas fa-download"></i> Export CSV';
    exportBtn.className = 'btn secondary btn-sm export-btn';
    exportBtn.style.cssText = 'position: absolute; top: 10px; right: 10px;';
    
    container.style.position = 'relative';
    container.appendChild(exportBtn);
    
    exportBtn.addEventListener('click', () => {
        const filename = `export_${Date.now()}.csv`;
        exportTableData(table, filename);
    });
}

// Advanced filtering functionality
function initializeAdvancedFilters() {
    const filterContainer = document.querySelector('.advanced-filters');
    if (!filterContainer) return;
    
    const filterInputs = filterContainer.querySelectorAll('input, select');
    const resetBtn = filterContainer.querySelector('.filter-reset');
    const applyBtn = filterContainer.querySelector('.filter-apply');
    
    // Auto-apply filters with debounce
    const debouncedFilter = AdminUtils.debounce(() => {
        applyFilters();
    }, 500);
    
    filterInputs.forEach(input => {
        input.addEventListener('input', debouncedFilter);
        input.addEventListener('change', debouncedFilter);
    });
    
    if (resetBtn) {
        resetBtn.addEventListener('click', resetFilters);
    }
    
    if (applyBtn) {
        applyBtn.addEventListener('click', applyFilters);
    }
}

function applyFilters() {
    const filterContainer = document.querySelector('.advanced-filters');
    const table = document.querySelector('.data-table');
    
    if (!filterContainer || !table) return;
    
    const filters = {};
    const filterInputs = filterContainer.querySelectorAll('input, select');
    
    // Collect filter values
    filterInputs.forEach(input => {
        if (input.value.trim()) {
            filters[input.name] = {
                value: input.value.trim().toLowerCase(),
                type: input.dataset.filterType || 'contains'
            };
        }
    });
    
    // Apply filters to table rows
    const rows = table.querySelectorAll('tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        let shouldShow = true;
        
        Object.keys(filters).forEach(filterName => {
            const filter = filters[filterName];
            const cellIndex = parseInt(filterContainer.querySelector(`[name="${filterName}"]`).dataset.columnIndex);
            const cellValue = row.cells[cellIndex].textContent.trim().toLowerCase();
            
            switch (filter.type) {
                case 'contains':
                    if (!cellValue.includes(filter.value)) {
                        shouldShow = false;
                    }
                    break;
                case 'equals':
                    if (cellValue !== filter.value) {
                        shouldShow = false;
                    }
                    break;
                case 'starts_with':
                    if (!cellValue.startsWith(filter.value)) {
                        shouldShow = false;
                    }
                    break;
                case 'greater_than':
                    const numValue = parseFloat(cellValue);
                    const filterNum = parseFloat(filter.value);
                    if (isNaN(numValue) || isNaN(filterNum) || numValue <= filterNum) {
                        shouldShow = false;
                    }
                    break;
                case 'less_than':
                    const numValue2 = parseFloat(cellValue);
                    const filterNum2 = parseFloat(filter.value);
                    if (isNaN(numValue2) || isNaN(filterNum2) || numValue2 >= filterNum2) {
                        shouldShow = false;
                    }
                    break;
            }
        });
        
        row.style.display = shouldShow ? '' : 'none';
        if (shouldShow) visibleCount++;
    });
    
    // Update result count
    const resultCount = document.querySelector('.filter-result-count');
    if (resultCount) {
        resultCount.textContent = `Showing ${visibleCount} of ${rows.length} records`;
    }
}

function resetFilters() {
    const filterContainer = document.querySelector('.advanced-filters');
    const table = document.querySelector('.data-table');
    
    if (!filterContainer || !table) return;
    
    // Clear all filter inputs
    const filterInputs = filterContainer.querySelectorAll('input, select');
    filterInputs.forEach(input => {
        if (input.type === 'checkbox') {
            input.checked = false;
        } else {
            input.value = '';
        }
    });
    
    // Show all rows
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        row.style.display = '';
    });
    
    // Update result count
    const resultCount = document.querySelector('.filter-result-count');
    if (resultCount) {
        resultCount.textContent = `Showing ${rows.length} of ${rows.length} records`;
    }
    
    showNotification('Filters reset', 'info', 1000);
}

// Data visualization helpers
function createProgressChart(containerId, data, options = {}) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const defaultOptions = {
        type: 'progress',
        animate: true,
        duration: 1000,
        colors: ['#28a745', '#ffc107', '#dc3545']
    };
    
    const config = Object.assign(defaultOptions, options);
    
    // Create progress bars
    container.innerHTML = '';
    data.forEach((item, index) => {
        const progressWrapper = document.createElement('div');
        progressWrapper.className = 'progress-wrapper';
        progressWrapper.style.marginBottom = '15px';
        
        const label = document.createElement('div');
        label.className = 'progress-label';
        label.textContent = `${item.label}: ${item.value}%`;
        label.style.marginBottom = '5px';
        label.style.fontWeight = 'bold';
        
        const progressBar = document.createElement('div');
        progressBar.className = 'progress-bar';
        progressBar.style.cssText = `
            width: 100%;
            height: 20px;
            background-color: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        `;
        
        const progressFill = document.createElement('div');
        progressFill.className = 'progress-fill';
        progressFill.style.cssText = `
            height: 100%;
            background-color: ${config.colors[index % config.colors.length]};
            width: 0%;
            transition: width ${config.duration}ms ease;
            border-radius: 10px;
        `;
        
        progressBar.appendChild(progressFill);
        progressWrapper.appendChild(label);
        progressWrapper.appendChild(progressBar);
        container.appendChild(progressWrapper);
        
        // Animate if enabled
        if (config.animate) {
            setTimeout(() => {
                progressFill.style.width = `${Math.min(100, Math.max(0, item.value))}%`;
            }, 100);
        } else {
            progressFill.style.width = `${Math.min(100, Math.max(0, item.value))}%`;
        }
    });
}

// System health monitoring
function initializeSystemHealth() {
    const healthContainer = document.querySelector('.system-health');
    if (!healthContainer) return;
    
    function checkSystemHealth() {
        makeRequest('ajax/system_health.php')
            .then(data => {
                updateSystemHealthDisplay(data);
            })
            .catch(error => {
                console.error('Failed to check system health:', error);
            });
    }
    
    // Check every 30 seconds
    setInterval(checkSystemHealth, 30000);
    
    // Initial check
    checkSystemHealth();
}

function updateSystemHealthDisplay(healthData) {
    const healthContainer = document.querySelector('.system-health');
    if (!healthContainer) return;
    
    healthContainer.innerHTML = '';
    
    healthData.checks.forEach(check => {
        const healthItem = document.createElement('div');
        healthItem.className = `health-item ${check.status}`;
        healthItem.style.cssText = `
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            background-color: ${check.status === 'healthy' ? '#d4edda' : 
                               check.status === 'warning' ? '#fff3cd' : '#f8d7da'};
            border: 1px solid ${check.status === 'healthy' ? '#c3e6cb' : 
                                check.status === 'warning' ? '#ffeeba' : '#f5c6cb'};
        `;
        
        const label = document.createElement('span');
        label.textContent = check.name;
        label.style.fontWeight = 'bold';
        
        const status = document.createElement('span');
        status.innerHTML = `<i class="fas fa-${check.status === 'healthy' ? 'check-circle' : 
                                              check.status === 'warning' ? 'exclamation-triangle' : 
                                              'times-circle'}"></i> ${check.message}`;
        status.style.color = check.status === 'healthy' ? '#155724' : 
                            check.status === 'warning' ? '#856404' : '#721c24';
        
        healthItem.appendChild(label);
        healthItem.appendChild(status);
        healthContainer.appendChild(healthItem);
    });
}

// File upload handler
function initializeFileUpload() {
    const uploadAreas = document.querySelectorAll('.file-upload-area');
    
    uploadAreas.forEach(area => {
        const input = area.querySelector('input[type="file"]');
        const dropZone = area.querySelector('.drop-zone');
        const progressBar = area.querySelector('.upload-progress');
        
        if (dropZone && input) {
            // Drag and drop functionality
            dropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('drag-over');
            });
            
            dropZone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('drag-over');
            });
            
            dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('drag-over');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    input.files = files;
                    handleFileUpload(input, progressBar);
                }
            });
            
            // Click to upload
            dropZone.addEventListener('click', () => {
                input.click();
            });
            
            // File input change
            input.addEventListener('change', function() {
                if (this.files.length > 0) {
                    handleFileUpload(this, progressBar);
                }
            });
        }
    });
}

function handleFileUpload(input, progressBar) {
    const files = input.files;
    if (files.length === 0) return;
    
    const formData = new FormData();
    Array.from(files).forEach(file => {
        formData.append('files[]', file);
    });
    
    // Show progress bar
    if (progressBar) {
        progressBar.style.display = 'block';
        progressBar.querySelector('.progress-fill').style.width = '0%';
    }
    
    // Upload with XMLHttpRequest for progress tracking
    const xhr = new XMLHttpRequest();
    
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable && progressBar) {
            const percentComplete = (e.loaded / e.total) * 100;
            progressBar.querySelector('.progress-fill').style.width = percentComplete + '%';
        }
    });
    
    xhr.addEventListener('load', function() {
        if (progressBar) {
            progressBar.style.display = 'none';
        }
        
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    showNotification('Files uploaded successfully', 'success');
                    // Refresh the page or update the UI as needed
                    if (response.reload) {
                        location.reload();
                    }
                } else {
                    showNotification('Upload failed: ' + response.error, 'error');
                }
            } catch (e) {
                showNotification('Upload completed but response was invalid', 'warning');
            }
        } else {
            showNotification('Upload failed with status: ' + xhr.status, 'error');
        }
    });
    
    xhr.addEventListener('error', function() {
        if (progressBar) {
            progressBar.style.display = 'none';
        }
        showNotification('Upload failed due to network error', 'error');
    });
    
    xhr.open('POST', 'ajax/file_upload.php');
    xhr.send(formData);
}

// Admin action confirmations
function confirmAction(message, callback) {
    const modal = document.createElement('div');
    modal.className = 'confirm-modal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 10000;
    `;
    
    const modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background-color: white;
        padding: 30px;
        border-radius: 8px;
        text-align: center;
        max-width: 400px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    `;
    
    modalContent.innerHTML = `
        <h3 style="margin-bottom: 20px; color: #333;">Confirm Action</h3>
        <p style="margin-bottom: 30px; color: #666;">${message}</p>
        <div>
            <button class="btn-confirm" style="
                background-color: #dc3545;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                margin-right: 10px;
                cursor: pointer;
            ">Confirm</button>
            <button class="btn-cancel" style="
                background-color: #6c757d;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
            ">Cancel</button>
        </div>
    `;
    
    modal.appendChild(modalContent);
    document.body.appendChild(modal);
    
    // Handle confirm
    modalContent.querySelector('.btn-confirm').addEventListener('click', () => {
        document.body.removeChild(modal);
        if (callback) callback(true);
    });
    
    // Handle cancel
    modalContent.querySelector('.btn-cancel').addEventListener('click', () => {
        document.body.removeChild(modal);
        if (callback) callback(false);
    });
    
    // Close on outside click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            document.body.removeChild(modal);
            if (callback) callback(false);
        }
    });
}

// Error handling for failed AJAX requests
window.addEventListener('unhandledrejection', function(event) {
    console.error('Unhandled promise rejection:', event.reason);
    showNotification('An error occurred. Please try again.', 'error');
});

// Performance monitoring
function measurePerformance() {
    if ('performance' in window) {
        window.addEventListener('load', function() {
            setTimeout(function() {
                const perfData = performance.getEntriesByType('navigation')[0];
                const loadTime = perfData.loadEventEnd - perfData.loadEventStart;
                
                console.log('Page load time:', loadTime + 'ms');
                
                // Send performance data to server if needed
                if (loadTime > 5000) { // If page takes more than 5 seconds
                    console.warn('Slow page load detected:', loadTime + 'ms');
                }
            }, 0);
        });
    }
}

measurePerformance();

// Date picker enhancement
function enhanceDatePickers() {
    const datePickers = document.querySelectorAll('input[type="date"], input[type="datetime-local"]');
    
    datePickers.forEach(picker => {
        // Add validation
        picker.addEventListener('change', function() {
            const value = new Date(this.value);
            const now = new Date();
            
            if (this.dataset.minDate === 'today' && value < now) {
                showNotification('Date cannot be in the past', 'error');
                this.value = '';
            }
        });
        
        // Add quick date buttons
        const quickDates = document.createElement('div');
        quickDates.className = 'quick-dates';
        quickDates.style.cssText = `
            margin-top: 5px;
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        `;
        
        const quickOptions = [
            { label: 'Today', days: 0 },
            { label: 'Tomorrow', days: 1 },
            { label: '1 Week', days: 7 },
            { label: '1 Month', days: 30 }
        ];
        
        quickOptions.forEach(option => {
            const btn = document.createElement('button');
            btn.textContent = option.label;
            btn.type = 'button';
            btn.className = 'quick-date-btn';
            btn.style.cssText = `
                padding: 5px 10px;
                font-size: 11px;
                background-color: #e9ecef;
                border: 1px solid #ced4da;
                border-radius: 3px;
                cursor: pointer;
            `;
            
            btn.addEventListener('click', () => {
                const date = new Date();
                date.setDate(date.getDate() + option.days);
                
                if (picker.type === 'date') {
                    picker.value = date.toISOString().split('T')[0];
                } else {
                    picker.value = date.toISOString().slice(0, 16);
                }
                
                picker.dispatchEvent(new Event('change'));
            });
            
            quickDates.appendChild(btn);
        });
        
        picker.parentNode.insertBefore(quickDates, picker.nextSibling);
    });
}

// Form submission with loading states
function enhanceFormSubmissions() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                // Store original text
                const originalText = submitBtn.innerHTML;
                
                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;
                
                // If this is an AJAX form, handle it
                if (this.classList.contains('ajax-form')) {
                    e.preventDefault();
                    submitAjaxForm(this, submitBtn, originalText);
                } else {
                    // For regular forms, restore button after a delay (in case of validation errors)
                    setTimeout(() => {
                        if (submitBtn.disabled) {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }
                    }, 5000);
                }
            }
        });
    });
}

function submitAjaxForm(form, submitBtn, originalText) {
    const formData = new FormData(form);
    
    fetch(form.action || window.location.href, {
        method: form.method || 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'Operation completed successfully', 'success');
            
            if (data.redirect) {
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 1000);
            } else if (data.reload) {
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                // Reset form if successful and no redirect
                form.reset();
            }
        } else {
            showNotification(data.error || 'Operation failed', 'error');
        }
    })
    .catch(error => {
        console.error('Form submission error:', error);
        showNotification('Form submission failed', 'error');
    })
    .finally(() => {
        // Restore button
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// Dynamic content loading
function loadContent(url, containerId, showLoader = true) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    if (showLoader) {
        container.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    }
    
    return fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
    })
    .then(html => {
        container.innerHTML = html;
        
        // Re-initialize any enhanced elements in the new content
        const newTables = container.querySelectorAll('.data-table');
        newTables.forEach(table => {
            addTableSorting(table);
            addRowHighlighting(table);
            addExportButton(table);
        });
        
        // Re-initialize other enhancements
        enhanceSearch();
        enhanceDatePickers();
    })
    .catch(error => {
        console.error('Content loading error:', error);
        container.innerHTML = '<div class="error-message">Failed to load content. Please try again.</div>';
        showNotification('Failed to load content', 'error');
    });
}

// Tab functionality
function initializeTabs() {
    const tabContainers = document.querySelectorAll('.tab-container');
    
    tabContainers.forEach(container => {
        const tabButtons = container.querySelectorAll('.tab-button');
        const tabPanes = container.querySelectorAll('.tab-pane');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetTab = this.dataset.tab;
                
                // Remove active class from all buttons and panes
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabPanes.forEach(pane => pane.classList.remove('active'));
                
                // Add active class to clicked button and corresponding pane
                this.classList.add('active');
                const targetPane = container.querySelector(`[data-tab-content="${targetTab}"]`);
                if (targetPane) {
                    targetPane.classList.add('active');
                }
                
                // Save active tab to localStorage
                localStorage.setItem(`admin_active_tab_${container.id}`, targetTab);
            });
        });
        
        // Restore active tab from localStorage
        const savedTab = localStorage.getItem(`admin_active_tab_${container.id}`);
        if (savedTab) {
            const savedButton = container.querySelector(`[data-tab="${savedTab}"]`);
            if (savedButton) {
                savedButton.click();
            }
        }
    });
}

// Utility functions
const AdminUtils = {
    formatBytes: function(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    },
    
    formatDate: function(date, format = 'YYYY-MM-DD') {
        const d = new Date(date);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        
        return format
            .replace('YYYY', year)
            .replace('MM', month)
            .replace('DD', day)
            .replace('HH', hours)
            .replace('mm', minutes);
    },
    
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    throttle: function(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },
    
    generateId: function() {
        return 'admin_' + Math.random().toString(36).substr(2, 9);
    },
    
    formatCurrency: function(amount, currency = 'USD') {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency
        }).format(amount);
    },
    
    validateForm: function(form) {
        const errors = [];
        const requiredInputs = form.querySelectorAll('[required]');
        
        requiredInputs.forEach(input => {
            if (!validateRequired(input.value)) {
                errors.push(`${input.name || input.placeholder} is required`);
                input.classList.add('error');
            } else {
                input.classList.remove('error');
            }
            
            // Email validation
            if (input.type === 'email' && input.value && !validateEmail(input.value)) {
                errors.push('Please enter a valid email address');
                input.classList.add('error');
            }
            
            // Phone validation
            if (input.type === 'tel' && input.value && !validatePhone(input.value)) {
                errors.push('Please enter a valid phone number');
                input.classList.add('error');
            }
        });
        
        return {
            isValid: errors.length === 0,
            errors: errors
        };
    }
};

// Make utility functions globally available
window.AdminUtils = AdminUtils;

// Initialize everything when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeSystemHealth();
    initializeFileUpload();
    initializeTabs();
    enhanceFormSubmissions();
    enhanceDatePickers();
    initializeAdvancedFilters();
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    // Clear any running timers or intervals
    const highestTimeoutId = setTimeout(';');
    for (let i = 0; i < highestTimeoutId; i++) {
        clearTimeout(i);
    }
});

// Global error handler
window.addEventListener('error', function(e) {
    console.error('Global error:', e.error);
    if (e.error && e.error.message && !e.error.message.includes('ResizeObserver')) {
        showNotification('An unexpected error occurred', 'error');
    }
});

// Service worker registration for offline capabilities
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js')
            .then(function(registration) {
                console.log('ServiceWorker registration successful');
            })
            .catch(function(err) {
                console.log('ServiceWorker registration failed: ', err);
            });
    });
}