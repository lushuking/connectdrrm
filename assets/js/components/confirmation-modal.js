/**
 * Confirmation Modal Component
 * Provides a flexible modal for confirmations, alerts, and user input
 */
class ConfirmationModal {
    constructor() {
        this.currentModal = null;
        this.init();
    }
    
    init() {
        // Close modal when clicking outside
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('confirmation-modal-overlay')) {
                this.hide();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.currentModal) {
                this.hide();
            }
        });
    }
    
    /**
     * Show confirmation modal
     * @param {Object} options - Modal configuration
     * @param {string} options.title - Modal title
     * @param {string} options.message - Modal message
     * @param {string} options.type - Modal type (info, warning, error, success)
     * @param {string} options.confirmText - Confirm button text
     * @param {string} options.cancelText - Cancel button text
     * @param {boolean} options.showCancel - Show cancel button
     * @param {Function} options.onConfirm - Confirm callback
     * @param {Function} options.onCancel - Cancel callback
     * @param {Object} options.input - Input field configuration
     * @param {boolean} options.dangerAction - Style as dangerous action
     */
    show(options = {}) {
        const defaults = {
            title: 'Confirm Action',
            message: 'Are you sure you want to continue?',
            type: 'info',
            confirmText: 'OK',
            cancelText: 'Cancel',
            showCancel: true,
            onConfirm: () => {},
            onCancel: () => {},
            input: null,
            dangerAction: false
        };
        
        const config = { ...defaults, ...options };
        
        // Remove existing modal
        this.hide();
        
        // Create modal HTML
        const modalHTML = this.createModalHTML(config);
        
        // Add to DOM
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.currentModal = document.querySelector('.confirmation-modal-overlay');
        
        // Add event listeners
        this.attachEventListeners(config);
        
        // Focus on input if present, otherwise focus on confirm button
        setTimeout(() => {
            const input = this.currentModal.querySelector('.confirmation-modal-input input, .confirmation-modal-input select, .confirmation-modal-input textarea');
            const confirmBtn = this.currentModal.querySelector('.btn-confirm');
            
            if (input) {
                input.focus();
                if (input.type === 'text' || input.tagName === 'TEXTAREA') {
                    input.select();
                }
            } else if (confirmBtn) {
                confirmBtn.focus();
            }
        }, 100);
        
        return this;
    }
    
    createModalHTML(config) {
        const iconMap = {
            info: 'info',
            warning: 'warning',
            error: 'error',
            success: 'check_circle'
        };
        
        const inputHTML = config.input ? this.createInputHTML(config.input) : '';
        
        const cancelButton = config.showCancel ? 
            `<button type="button" class="btn btn-secondary btn-cancel">${config.cancelText}</button>` : '';
        
        const confirmButtonClass = config.dangerAction ? 'btn-danger' : 'btn-primary';
        
        return `
            <div class="confirmation-modal-overlay">
                <div class="confirmation-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
                    <div class="confirmation-modal-header">
                        <div class="confirmation-modal-icon ${config.type}">
                            <span class="material-icons">${iconMap[config.type] || 'info'}</span>
                        </div>
                        <h3 class="confirmation-modal-title" id="modal-title">${config.title}</h3>
                    </div>
                    <div class="confirmation-modal-body">
                        <p class="confirmation-modal-message">${config.message}</p>
                        ${inputHTML}
                    </div>
                    <div class="confirmation-modal-footer">
                        ${cancelButton}
                        <button type="button" class="btn ${confirmButtonClass} btn-confirm">${config.confirmText}</button>
                    </div>
                </div>
            </div>
        `;
    }
    
    createInputHTML(inputConfig) {
        const { type, label, placeholder, value, required, options } = inputConfig;
        
        let inputField = '';
        
        switch (type) {
            case 'text':
            case 'email':
            case 'number':
            case 'password':
                inputField = `
                    <input type="${type}" 
                           placeholder="${placeholder || ''}" 
                           value="${value || ''}"
                           ${required ? 'required' : ''}
                           autocomplete="off">
                `;
                break;
                
            case 'textarea':
                inputField = `
                    <textarea placeholder="${placeholder || ''}" 
                              ${required ? 'required' : ''}
                              rows="3">${value || ''}</textarea>
                `;
                break;
                
            case 'select':
                const optionsList = options.map(opt => 
                    `<option value="${opt.value}" ${opt.value === value ? 'selected' : ''}>${opt.label}</option>`
                ).join('');
                
                inputField = `
                    <select ${required ? 'required' : ''}>
                        <option value="">Select an option</option>
                        ${optionsList}
                    </select>
                `;
                break;
        }
        
        return `
            <div class="confirmation-modal-input">
                <label>${label}</label>
                ${inputField}
            </div>
        `;
    }
    
    attachEventListeners(config) {
        const confirmBtn = this.currentModal.querySelector('.btn-confirm');
        const cancelBtn = this.currentModal.querySelector('.btn-cancel');
        
        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => {
                const inputValue = this.getInputValue();
                
                // Validate required input
                if (config.input && config.input.required && !inputValue) {
                    const inputField = this.currentModal.querySelector('.confirmation-modal-input input, .confirmation-modal-input select, .confirmation-modal-input textarea');
                    inputField.focus();
                    this.showInputError('This field is required');
                    return;
                }
                
                config.onConfirm(inputValue);
                this.hide();
            });
        }
        
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                config.onCancel();
                this.hide();
            });
        }
        
        // Handle Enter key for confirmation
        this.currentModal.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                confirmBtn.click();
            }
        });
    }
    
    getInputValue() {
        if (!this.currentModal) return null;
        
        const input = this.currentModal.querySelector('.confirmation-modal-input input, .confirmation-modal-input select, .confirmation-modal-input textarea');
        return input ? input.value.trim() : null;
    }
    
    showInputError(message) {
        // Remove existing error
        const existingError = this.currentModal.querySelector('.input-error');
        if (existingError) {
            existingError.remove();
        }
        
        // Add new error
        const inputContainer = this.currentModal.querySelector('.confirmation-modal-input');
        if (inputContainer) {
            const errorElement = document.createElement('div');
            errorElement.className = 'input-error';
            errorElement.style.cssText = 'color: #EF4444; font-size: 0.8rem; margin-top: 0.5rem;';
            errorElement.textContent = message;
            inputContainer.appendChild(errorElement);
        }
    }
    
    hide() {
        if (!this.currentModal) return;
        
        this.currentModal.classList.add('fade-out');
        
        setTimeout(() => {
            if (this.currentModal && this.currentModal.parentNode) {
                this.currentModal.remove();
            }
            this.currentModal = null;
        }, 200);
        
        return this;
    }
    
    // Convenience methods
    confirm(title, message, onConfirm, onCancel) {
        return this.show({
            title,
            message,
            type: 'info',
            onConfirm,
            onCancel
        });
    }
    
    alert(title, message, type = 'info') {
        return this.show({
            title,
            message,
            type,
            showCancel: false,
            confirmText: 'OK',
            onConfirm: () => {
                // Just close the modal
            }
        });
    }
    
    prompt(title, message, inputConfig, onConfirm, onCancel) {
        return this.show({
            title,
            message,
            type: 'info',
            input: inputConfig,
            onConfirm,
            onCancel
        });
    }
    
    danger(title, message, onConfirm, onCancel) {
        return this.show({
            title,
            message,
            type: 'warning',
            dangerAction: true,
            confirmText: 'Delete',
            onConfirm,
            onCancel
        });
    }
}

// Create global instance
window.confirmationModal = new ConfirmationModal();

// Export for modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ConfirmationModal;
}