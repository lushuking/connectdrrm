/**
 * Request Document Generator Component
 * Generates formal request documents similar to template.html
 */
class RequestDocumentGenerator {
    constructor() {
        this.currentRequestData = null;
        this.isGenerating = false;
    }

    /**
     * Initialize the document generator
     */
    init() {
        this.bindEvents();
    }

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Listen for document generation requests
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-action="generate-document"]')) {
                e.preventDefault();
                const requestId = e.target.dataset.requestId;
                this.generateDocument(requestId);
            }
        });

        // Listen for document viewing requests
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-action="view-document"]')) {
                e.preventDefault();
                const requestId = e.target.dataset.requestId;
                this.viewDocument(requestId);
            }
        });
    }

    /**
     * Generate a formal request document
     * @param {string} requestId - The request ID
     */
    async generateDocument(requestId) {
        if (this.isGenerating) return;
        
        this.isGenerating = true;
        
        try {
            // Fetch request data
            const requestData = await this.fetchRequestData(requestId);
            if (!requestData) {
                throw new Error('Request data not found');
            }

            this.currentRequestData = requestData;
            
            // Show document generation modal
            this.showDocumentGeneratorModal(requestData);
            
        } catch (error) {
            console.error('Error generating document:', error);
            this.showError('Failed to generate document: ' + error.message);
        } finally {
            this.isGenerating = false;
        }
    }

    /**
     * View an existing generated document
     * @param {string} requestId - The request ID
     */
    async viewDocument(requestId) {
        try {
            // Fetch request data
            const requestData = await this.fetchRequestData(requestId);
            if (!requestData) {
                throw new Error('Request data not found');
            }

            // Show document viewer modal
            this.showDocumentViewerModal(requestData);
            
        } catch (error) {
            console.error('Error viewing document:', error);
            this.showError('Failed to load document: ' + error.message);
        }
    }

    /**
     * Fetch request data from the server
     * @param {string} requestId - The request ID
     * @returns {Promise<Object>} Request data
     */
    async fetchRequestData(requestId) {
        try {
            const response = await fetch(`config/get_request_details.php?requestId=${requestId}`);
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error?.message || 'Failed to fetch request data');
            }
            
            return result.data;
        } catch (error) {
            console.error('Error fetching request data:', error);
            throw error;
        }
    }

    /**
     * Show the document generation modal
     * @param {Object} requestData - The request data
     */
    showDocumentGeneratorModal(requestData) {
        const modal = this.createDocumentGeneratorModal(requestData);
        document.body.appendChild(modal);
        
        // Show modal
        modal.style.display = 'flex';
        setTimeout(() => {
            modal.classList.add('show');
        }, 10);
    }

    /**
     * Show the document viewer modal
     * @param {Object} requestData - The request data
     */
    showDocumentViewerModal(requestData) {
        const modal = this.createDocumentViewerModal(requestData);
        document.body.appendChild(modal);
        
        // Show modal
        modal.style.display = 'flex';
        setTimeout(() => {
            modal.classList.add('show');
        }, 10);
    }

    /**
     * Create the document generation modal
     * @param {Object} requestData - The request data
     * @returns {HTMLElement} Modal element
     */
    createDocumentGeneratorModal(requestData) {
        const modal = document.createElement('div');
        modal.className = 'request-document-modal';
        modal.innerHTML = `
            <div class="modal-backdrop"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Generate Request Document</h2>
                    <button class="close-btn" data-action="close-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="document-form">
                        <div class="form-section">
                            <h3>📦 Resource Information</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Resource Name</label>
                                    <input type="text" id="resourceName" value="${requestData.resourceName || ''}" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Quantity Needed</label>
                                    <input type="number" id="quantityNeeded" value="${requestData.quantity || ''}" readonly>
                                </div>
                                <div class="form-group">
                                    <label>From Municipality</label>
                                    <input type="text" id="fromMunicipality" value="${requestData.fromMunicipality || ''}" readonly>
                                </div>
                                <div class="form-group">
                                    <label>To Municipality</label>
                                    <input type="text" id="toMunicipality" value="${requestData.toMunicipality || ''}" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>📋 Request Details</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Priority Level</label>
                                    <select id="priorityLevel">
                                        <option value="low" ${requestData.priority === 'low' ? 'selected' : ''}>🟢 Low Priority</option>
                                        <option value="medium" ${requestData.priority === 'medium' ? 'selected' : ''}>🟡 Medium Priority</option>
                                        <option value="high" ${requestData.priority === 'high' ? 'selected' : ''}>🟠 High Priority</option>
                                        <option value="critical" ${requestData.priority === 'critical' ? 'selected' : ''}>🔴 Critical Priority</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Urgency</label>
                                    <select id="urgency">
                                        <option value="normal">Normal (1-3 days)</option>
                                        <option value="urgent">Urgent (Within 24 hours)</option>
                                        <option value="emergency">Emergency (Immediate)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Delivery Date</label>
                                    <input type="date" id="deliveryDate">
                                </div>
                                <div class="form-group">
                                    <label>Delivery Location</label>
                                    <input type="text" id="deliveryLocation" placeholder="Enter delivery address">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>📞 Contact Information</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Requestor Name</label>
                                    <input type="text" id="requestorName" placeholder="Enter requestor name">
                                </div>
                                <div class="form-group">
                                    <label>Contact Phone</label>
                                    <input type="tel" id="contactPhone" placeholder="Enter phone number">
                                </div>
                                <div class="form-group">
                                    <label>Contact Email</label>
                                    <input type="email" id="contactEmail" placeholder="Enter email address">
                                </div>
                                <div class="form-group">
                                    <label>Purpose of Request</label>
                                    <select id="purpose">
                                        <option value="">Select Purpose</option>
                                        <option value="Emergency Response">Emergency Response</option>
                                        <option value="Disaster Relief">Disaster Relief</option>
                                        <option value="Training Exercise">Training Exercise</option>
                                        <option value="Maintenance">Maintenance</option>
                                        <option value="Preparedness">Preparedness</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>📝 Additional Information</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Expected Duration</label>
                                    <div style="display: flex; gap: 8px;">
                                        <input type="number" id="expectedDurationNumber" min="1" max="365" placeholder="Number" style="flex: 1;">
                                        <select id="expectedDurationUnit" style="width: 120px;">
                                            <option value="">Unit</option>
                                            <option value="days">days</option>
                                            <option value="weeks">weeks</option>
                                            <option value="months">months</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Return Date</label>
                                    <input type="date" id="returnDate">
                                </div>
                                <div class="form-group">
                                    <label>Transportation Method</label>
                                    <select id="transportMethod">
                                        <option value="">Select Method</option>
                                        <option value="Pickup (We will collect)">Pickup (We will collect)</option>
                                        <option value="Delivery (Please deliver)">Delivery (Please deliver)</option>
                                        <option value="Special Arrangement">Special Arrangement</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Approving Authority</label>
                                    <input type="text" id="approvingAuthority" placeholder="e.g., CERILO B. CARCUEVA, MAGD">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Additional Notes</label>
                                <textarea id="additionalNotes" rows="3" placeholder="Enter any additional notes or special requirements"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-action="close-modal">Cancel</button>
                    <button class="btn btn-primary" data-action="preview-document">Preview Document</button>
                </div>
            </div>
        `;

        // Bind modal events
        this.bindModalEvents(modal, requestData);
        
        // Parse and populate duration fields if requestData has expectedDuration
        if (requestData.expectedDuration) {
            const match = requestData.expectedDuration.match(/^(\d+)-(days|weeks|months)$/);
            if (match) {
                const numberField = modal.querySelector('#expectedDurationNumber');
                const unitField = modal.querySelector('#expectedDurationUnit');
                if (numberField) numberField.value = match[1];
                if (unitField) unitField.value = match[2];
                // Set max based on unit
                if (numberField && unitField && unitField.value) {
                    if (unitField.value === 'days') {
                        numberField.max = 365;
                    } else if (unitField.value === 'weeks') {
                        numberField.max = 52;
                    } else if (unitField.value === 'months') {
                        numberField.max = 12;
                    }
                }
            }
        }
        
        // Add event listener for unit change to update max limit
        const unitField = modal.querySelector('#expectedDurationUnit');
        if (unitField) {
            unitField.addEventListener('change', function() {
                const numberField = modal.querySelector('#expectedDurationNumber');
                if (numberField) {
                    const unit = this.value;
                    if (unit === 'days') {
                        numberField.max = 365;
                    } else if (unit === 'weeks') {
                        numberField.max = 52;
                    } else if (unit === 'months') {
                        numberField.max = 12;
                    } else {
                        numberField.max = 365; // Default
                    }
                    // If current value exceeds new max, set it to max
                    if (numberField.value && parseInt(numberField.value) > parseInt(numberField.max)) {
                        numberField.value = numberField.max;
                    }
                }
            });
        }
        
        return modal;
    }

    /**
     * Create the document viewer modal
     * @param {Object} requestData - The request data
     * @returns {HTMLElement} Modal element
     */
    createDocumentViewerModal(requestData) {
        const modal = document.createElement('div');
        modal.className = 'request-document-modal';
        modal.innerHTML = `
            <div class="modal-backdrop"></div>
            <div class="modal-content document-viewer">
                <div class="modal-header">
                    <h2>Request Document - ${requestData.resourceName || 'Resource Request'}</h2>
                    <button class="close-btn" data-action="close-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="document-viewer-actions">
                        <button class="btn btn-primary" data-action="print-document">
                            <i class="fas fa-print"></i> Print Document
                        </button>
                        <button class="btn btn-secondary" data-action="download-document">
                            <i class="fas fa-download"></i> Download PDF
                        </button>
                    </div>
                    <div class="document-content" id="documentContent">
                        ${this.generateDocumentHTML(requestData)}
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-action="close-modal">Close</button>
                </div>
            </div>
        `;

        // Bind modal events
        this.bindViewerModalEvents(modal, requestData);
        
        return modal;
    }

    /**
     * Generate the HTML content for the document
     * @param {Object} requestData - The request data
     * @returns {string} HTML content
     */
    generateDocumentHTML(requestData) {
        const today = new Date().toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });

        const priorityLabels = {
            low: 'Low Priority',
            medium: 'Medium Priority',
            high: 'High Priority',
            critical: 'Critical Priority'
        };

        const urgencyLabels = {
            normal: 'Normal (1-3 days)',
            urgent: 'Urgent (Within 24 hours)',
            emergency: 'Emergency (Immediate)'
        };

        return `
            <div class="formal-document">
                <!-- Header -->
                <div class="document-header">
                    <p class="text-sm">Republic of the Philippines</p>
                    <p class="text-sm">Province of Zamboanga Del Sur</p>
                    <p class="text-sm font-bold">MUNICIPALITY OF ${(requestData.fromMunicipality || '').toUpperCase()}</p>
                    <p class="text-sm">=7011=</p>
                    <p class="text-xs">MUNICIPAL DISASTER RISK REDUCTION & MANAGEMENT OFFICE</p>
                </div>

                <!-- Date -->
                <p class="mb-4">${today}</p>

                <!-- Addressee -->
                <div class="mb-4">
                    <p>Mr. Faro Antonio Olaguera</p>
                    <p>OIC-PDRRMO</p>
                    <p>ZAMBOANGA DEL SUR</p>
                </div>

                <!-- Greeting -->
                <p class="mb-4">Good day Sir!</p>

                <!-- Main Body -->
                <p class="mb-4 text-justify">
                    The ${requestData.fromMunicipality || 'requesting municipality'} respectfully requests ${requestData.quantity || '1'} unit(s) of ${requestData.resourceName || 'the requested resource'} from ${requestData.toMunicipality || 'the providing municipality'} with ${priorityLabels[requestData.priority] || 'Medium Priority'} priority. This request is classified as ${priorityLabels[requestData.priority] || 'Medium Priority'} with ${urgencyLabels[requestData.urgency] || 'Normal (1-3 days)'} urgency level. The resource is needed for ${requestData.purpose || 'operational'} purposes and is expected to be utilized for ${requestData.expectedDuration || 'the required duration'}.
                </p>

                <p class="mb-4 text-justify">
                    We kindly request the delivery of the aforementioned resource to ${requestData.deliveryLocation || 'our designated location'} on or before ${requestData.deliveryDate || 'the requested date'}. The transportation arrangement will be through ${requestData.transportMethod || 'mutual arrangement'}, and we anticipate returning the resource by ${requestData.returnDate || 'the agreed return date'}.
                </p>

                ${requestData.additionalNotes ? `
                    <p class="mb-4 text-justify">
                        ${requestData.additionalNotes}
                    </p>
                ` : ''}

                <p class="mb-4 text-justify">
                    For coordination and follow-up purposes, please contact ${requestData.requestorName || 'our designated contact person'} at ${requestData.contactPhone || 'our contact number'} or via email at ${requestData.contactEmail || 'our email address'}.
                </p>

                <!-- Closing -->
                <p class="mb-8 text-justify">
                    Thank you in advance and we look forward to a favorable and considerable response regarding this matter.
                </p>

                <!-- Signatures -->
                <div class="mb-12">
                    <p>Prepared by:</p>
                    <br />
                    <p class="font-bold">${requestData.requestorName || '_________________'}</p>
                    <p>Requestor</p>
                </div>

                <div>
                    <p>Approved by:</p>
                    <br />
                    <p class="font-bold">${requestData.approvingAuthority || '_________________'}</p>
                    <p>Municipal Disaster Risk Reduction & Management Officer</p>
                </div>
            </div>
        `;
    }

    /**
     * Bind events for the document generator modal
     * @param {HTMLElement} modal - The modal element
     * @param {Object} requestData - The request data
     */
    bindModalEvents(modal, requestData) {
        // Close modal
        modal.addEventListener('click', (e) => {
            if (e.target.matches('[data-action="close-modal"]') || e.target.classList.contains('modal-backdrop')) {
                this.closeModal(modal);
            }
        });

        // Preview document
        modal.addEventListener('click', (e) => {
            if (e.target.matches('[data-action="preview-document"]')) {
                this.previewDocument(modal, requestData);
            }
        });
    }

    /**
     * Bind events for the document viewer modal
     * @param {HTMLElement} modal - The modal element
     * @param {Object} requestData - The request data
     */
    bindViewerModalEvents(modal, requestData) {
        // Close modal
        modal.addEventListener('click', (e) => {
            if (e.target.matches('[data-action="close-modal"]') || e.target.classList.contains('modal-backdrop')) {
                this.closeModal(modal);
            }
        });

        // Print document
        modal.addEventListener('click', (e) => {
            if (e.target.matches('[data-action="print-document"]')) {
                this.printDocument(modal);
            }
        });

        // Download document
        modal.addEventListener('click', (e) => {
            if (e.target.matches('[data-action="download-document"]')) {
                this.downloadDocument(modal, requestData);
            }
        });
    }

    /**
     * Preview the generated document
     * @param {HTMLElement} modal - The modal element
     * @param {Object} requestData - The request data
     */
    previewDocument(modal, requestData) {
        // Get form data
        const formData = this.getFormData(modal);
        
        // Merge with request data
        const mergedData = { ...requestData, ...formData };
        
        // Close current modal
        this.closeModal(modal);
        
        // Show preview modal
        this.showDocumentViewerModal(mergedData);
    }

    /**
     * Get form data from the modal
     * @param {HTMLElement} modal - The modal element
     * @returns {Object} Form data
     */
    getFormData(modal) {
        const formData = {};
        const inputs = modal.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            if (input.id) {
                // Skip the duration number and unit fields - we'll combine them
                if (input.id === 'expectedDurationNumber' || input.id === 'expectedDurationUnit') {
                    return;
                }
                formData[input.id] = input.value;
            }
        });
        
        // Combine duration fields into expectedDuration
        const durationNumber = modal.querySelector('#expectedDurationNumber')?.value;
        const durationUnit = modal.querySelector('#expectedDurationUnit')?.value;
        if (durationNumber && durationUnit) {
            formData.expectedDuration = `${durationNumber}-${durationUnit}`;
        }
        
        return formData;
    }

    /**
     * Print the document
     * @param {HTMLElement} modal - The modal element
     */
    printDocument(modal) {
        const printContent = modal.querySelector('.document-content').innerHTML;
        const printWindow = window.open('', '_blank');
        
        printWindow.document.write(`
            <html>
                <head>
                    <title>Request Document</title>
                    <style>
                        body { font-family: 'Times New Roman', serif; margin: 20px; }
                        .formal-document { max-width: 800px; margin: 0 auto; }
                        .document-header { text-align: center; margin-bottom: 30px; }
                        .text-sm { font-size: 14px; }
                        .text-xs { font-size: 12px; }
                        .font-bold { font-weight: bold; }
                        .mb-4 { margin-bottom: 16px; }
                        .mb-8 { margin-bottom: 32px; }
                        .mb-12 { margin-bottom: 48px; }
                        .text-justify { text-align: justify; }
                    </style>
                </head>
                <body>
                    ${printContent}
                </body>
            </html>
        `);
        
        printWindow.document.close();
        printWindow.print();
    }

    /**
     * Download the document as PDF
     * @param {HTMLElement} modal - The modal element
     * @param {Object} requestData - The request data
     */
    downloadDocument(modal, requestData) {
        // For now, just trigger print dialog
        // In a real implementation, you would use a PDF generation library
        this.printDocument(modal);
    }

    /**
     * Close the modal
     * @param {HTMLElement} modal - The modal element
     */
    closeModal(modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.remove();
        }, 300);
    }

    /**
     * Show error message
     * @param {string} message - Error message
     */
    showError(message) {
        // You can implement a toast notification or alert here
        alert(message);
    }
}

// Initialize the document generator when the page loads
document.addEventListener('DOMContentLoaded', () => {
    window.requestDocumentGenerator = new RequestDocumentGenerator();
    window.requestDocumentGenerator.init();
});

