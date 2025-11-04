// Payroll HR System - Main Application JavaScript

class PayrollApp {
    constructor() {
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupAjaxDefaults();
        this.setupDataTables();
        this.setupNotifications();
    }

    setupEventListeners() {
        // Auto-format currency inputs
        document.addEventListener('input', function(e) {
            if (e.target.type === 'number' && e.target.classList.contains('currency-input')) {
                this.formatCurrencyInput(e.target);
            }
        }.bind(this));

        // Auto-format phone numbers
        document.addEventListener('input', function(e) {
            if (e.target.type === 'tel') {
                this.formatPhoneNumber(e.target);
            }
        }.bind(this));

        // Confirm destructive actions
        document.addEventListener('click', function(e) {
            if (e.target.closest('[data-confirm]')) {
                const message = e.target.closest('[data-confirm]').getAttribute('data-confirm');
                if (!confirm(message)) {
                    e.preventDefault();
                }
            }
        });

        // Tooltip initialization
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    setupAjaxDefaults() {
        // Add CSRF token to all AJAX requests
        $.ajaxSetup({
            beforeSend: function(xhr) {
                const token = document.querySelector('meta[name="csrf-token"]');
                if (token) {
                    xhr.setRequestHeader('X-CSRF-TOKEN', token.getAttribute('content'));
                }
            }
        });

        // Global AJAX error handling
        $(document).ajaxError(function(event, jqXHR, ajaxSettings, thrownError) {
            if (jqXHR.status === 401) {
                window.location.href = '/login.php?timeout=1';
            } else if (jqXHR.status === 403) {
                this.showNotification('Access denied. Please check your permissions.', 'error');
            } else if (jqXHR.status === 500) {
                this.showNotification('Server error occurred. Please try again.', 'error');
            }
        }.bind(this));
    }

    setupDataTables() {
        // Default configuration for all DataTables
        if ($.fn.DataTable) {
            $.extend(true, $.fn.dataTable.defaults, {
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    zeroRecords: "No matching records found",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                },
                pageLength: 25,
                responsive: true,
                stateSave: true,
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
            });
        }
    }

    setupNotifications() {
        // Create notification container if it doesn't exist
        if (!document.getElementById('notification-container')) {
            const container = document.createElement('div');
            container.id = 'notification-container';
            container.className = 'position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }
    }

    formatCurrencyInput(input) {
        const value = input.value.replace(/[^\d.]/g, '');
        if (value) {
            const formatted = new Intl.NumberFormat('en-NG', {
                style: 'currency',
                currency: 'NGN'
            }).format(value);
            input.value = formatted;
        }
    }

    formatPhoneNumber(input) {
        let value = input.value.replace(/\D/g, '');
        
        if (value.startsWith('0')) {
            value = '+234' + value.substring(1);
        } else if (value.startsWith('234')) {
            value = '+' + value;
        }
        
        input.value = value;
    }

    showNotification(message, type = 'info', duration = 5000) {
        const container = document.getElementById('notification-container');
        const notification = document.createElement('div');
        
        const alertClass = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info'
        }[type] || 'alert-info';
        
        notification.className = `alert ${alertClass} alert-dismissible fade show`;
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        container.appendChild(notification);
        
        // Auto-remove after duration
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, duration);
    }

    // Utility function to format numbers as Nigerian currency
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-NG', {
            style: 'currency',
            currency: 'NGN'
        }).format(amount);
    }

    // Utility function to format dates
    formatDate(date, format = 'medium') {
        const dateObj = new Date(date);
        return dateObj.toLocaleDateString('en-NG', {
            year: 'numeric',
            month: format === 'short' ? 'short' : 'long',
            day: 'numeric'
        });
    }

    // Calculate age from date of birth
    calculateAge(birthDate) {
        const today = new Date();
        const birth = new Date(birthDate);
        let age = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
            age--;
        }
        
        return age;
    }

    // Validate Nigerian phone number
    validatePhoneNumber(phone) {
        const pattern = /^(?:\+234|0)[789][01]\d{8}$/;
        return pattern.test(phone);
    }

    // Validate email
    validateEmail(email) {
        const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return pattern.test(email);
    }

    // Validate BVN (11 digits)
    validateBVN(bvn) {
        return /^\d{11}$/.test(bvn);
    }

    // Calculate PAYE tax for Nigeria
    calculatePAYE(annualIncome) {
        let tax = 0;
        
        if (annualIncome <= 300000) {
            tax = annualIncome * 0.07;
        } else if (annualIncome <= 600000) {
            tax = 21000 + ((annualIncome - 300000) * 0.11);
        } else if (annualIncome <= 1100000) {
            tax = 54000 + ((annualIncome - 600000) * 0.15);
        } else if (annualIncome <= 1600000) {
            tax = 129000 + ((annualIncome - 1100000) * 0.19);
        } else {
            tax = 224000 + ((annualIncome - 1600000) * 0.21);
        }
        
        return tax;
    }

    // Calculate pension contribution
    calculatePension(basicSalary, employeeRate = 8, employerRate = 10) {
        return {
            employee: (basicSalary * employeeRate) / 100,
            employer: (basicSalary * employerRate) / 100,
            total: (basicSalary * (employeeRate + employerRate)) / 100
        };
    }

    // Export data to various formats
    exportData(tableId, format = 'csv') {
        const table = document.getElementById(tableId);
        if (!table) {
            this.showNotification('Table not found', 'error');
            return;
        }

        let data, filename, link;

        if (format === 'csv') {
            data = this.tableToCSV(table);
            filename = 'export.csv';
        } else if (format === 'excel') {
            data = this.tableToExcel(table);
            filename = 'export.xls';
        } else {
            data = this.tableToJSON(table);
            filename = 'export.json';
        }

        link = document.createElement('a');
        link.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(data));
        link.setAttribute('download', filename);
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    tableToCSV(table) {
        const rows = table.querySelectorAll('tr');
        const csv = [];
        
        for (let i = 0; i < rows.length; i++) {
            const row = [], cols = rows[i].querySelectorAll('td, th');
            
            for (let j = 0; j < cols.length; j++) {
                let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
                data = data.replace(/"/g, '""');
                row.push('"' + data + '"');
            }
            
            csv.push(row.join(','));
        }
        
        return csv.join('\n');
    }

    tableToExcel(table) {
        // Simple Excel format (tab-separated values)
        const rows = table.querySelectorAll('tr');
        const excel = [];
        
        for (let i = 0; i < rows.length; i++) {
            const row = [], cols = rows[i].querySelectorAll('td, th');
            
            for (let j = 0; j < cols.length; j++) {
                row.push(cols[j].innerText);
            }
            
            excel.push(row.join('\t'));
        }
        
        return excel.join('\n');
    }

    tableToJSON(table) {
        const rows = table.querySelectorAll('tr');
        const headers = [];
        const json = [];
        
        // Get headers
        const headerRow = rows[0].querySelectorAll('th');
        for (let i = 0; i < headerRow.length; i++) {
            headers.push(headerRow[i].innerText.trim());
        }
        
        // Get data rows
        for (let i = 1; i < rows.length; i++) {
            const row = {};
            const cols = rows[i].querySelectorAll('td');
            
            for (let j = 0; j < headers.length; j++) {
                if (cols[j]) {
                    row[headers[j]] = cols[j].innerText.trim();
                }
            }
            
            json.push(row);
        }
        
        return JSON.stringify(json, null, 2);
    }

    // Session management
    checkSession() {
        const lastActivity = localStorage.getItem('lastActivity');
        const now = Date.now();
        const sessionTimeout = 30 * 60 * 1000; // 30 minutes
        
        if (lastActivity && (now - lastActivity > sessionTimeout)) {
            this.showNotification('Session expired. Please login again.', 'warning');
            setTimeout(() => {
                window.location.href = '/login.php?timeout=1';
            }, 2000);
        }
        
        localStorage.setItem('lastActivity', now);
    }

    // Initialize session monitoring
    initSessionMonitor() {
        this.checkSession();
        
        // Update last activity on user interaction
        ['click', 'keypress', 'scroll', 'mousemove'].forEach(event => {
            document.addEventListener(event, () => {
                localStorage.setItem('lastActivity', Date.now());
            });
        });
        
        // Check session every minute
        setInterval(() => {
            this.checkSession();
        }, 60000);
    }
}

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.payrollApp = new PayrollApp();
    window.payrollApp.initSessionMonitor();
    
    // Add loading indicator for AJAX requests
    $(document).ajaxStart(function() {
        $('#loadingIndicator').show();
    });
    
    $(document).ajaxStop(function() {
        $('#loadingIndicator').hide();
    });
});

// Global utility functions
function showLoading(message = 'Loading...') {
    const loadingDiv = document.createElement('div');
    loadingDiv.id = 'globalLoading';
    loadingDiv.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center';
    loadingDiv.style.cssText = 'background: rgba(0,0,0,0.5); z-index: 9999;';
    loadingDiv.innerHTML = `
        <div class="bg-white rounded p-4 text-center">
            <div class="spinner-border text-primary mb-2"></div>
            <div>${message}</div>
        </div>
    `;
    document.body.appendChild(loadingDiv);
}

function hideLoading() {
    const loadingDiv = document.getElementById('globalLoading');
    if (loadingDiv) {
        loadingDiv.remove();
    }
}

// Debounce function for search inputs
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Throttle function for scroll events
function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}