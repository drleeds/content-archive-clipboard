/**
 * Content Archive Clipboard - Frontend JavaScript
 */

(function($) {
    'use strict';
    
    class ContentArchiveClipboard {
        constructor(container) {
            this.container = $(container);
            this.postsContainer = this.container.find('.cac-posts-list');
            this.loadingIndicator = this.container.find('.cac-loading');
            this.statusElement = this.container.find('.cac-status');
            this.atts = this.container.data('atts') || {};
            
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.setupAccessibility();
        }
        
        bindEvents() {
            // Copy to clipboard
            this.container.find('.cac-copy-btn').on('click', (e) => {
                this.copyToClipboard(e.target);
            });
            
            // Filter functionality
            this.container.find('.cac-filter-btn').on('click', () => {
                this.filterPosts();
            });
            
            this.container.find('.cac-clear-btn').on('click', () => {
                this.clearFilters();
            });
            
            // Export functionality
            this.container.find('.cac-export-btn').on('click', (e) => {
                this.toggleExportOptions(e.target);
            });
            
            this.container.find('.cac-export-csv').on('click', () => {
                this.exportPosts('csv');
            });
            
            this.container.find('.cac-export-md').on('click', () => {
                this.exportPosts('markdown');
            });
            
            // Filter on Enter key
            this.container.find('.cac-date-filter, .cac-category-filter').on('keypress', (e) => {
                if (e.which === 13) {
                    this.filterPosts();
                }
            });
            
            // Close export options when clicking outside
            $(document).on('click', (e) => {
                if (!$(e.target).closest('.cac-export-group').length) {
                    this.container.find('.cac-export-options').hide();
                }
            });
        }
        
        setupAccessibility() {
            // Add ARIA attributes for better accessibility
            this.container.find('.cac-copy-btn').attr('aria-describedby', this.statusElement.attr('id') || 'cac-status');
            
            // Ensure proper keyboard navigation
            this.container.find('button, input, select').attr('tabindex', '0');
        }
        
        copyToClipboard(button) {
            const $button = $(button);
            const originalText = $button.text();
            
            // Get the post list text
            const postsText = this.getPostsAsText();
            
            if (!postsText) {
                this.showStatus(cac_ajax.strings.no_posts, 'error');
                return;
            }
            
            // Use the modern Clipboard API if available
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(postsText).then(() => {
                    this.showStatus(cac_ajax.strings.copy_success, 'success');
                    this.animateButton($button, originalText);
                }).catch(() => {
                    this.fallbackCopyToClipboard(postsText, $button, originalText);
                });
            } else {
                this.fallbackCopyToClipboard(postsText, $button, originalText);
            }
        }
        
        fallbackCopyToClipboard(text, $button, originalText) {
            // Fallback for older browsers or non-secure contexts
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    this.showStatus(cac_ajax.strings.copy_success, 'success');
                    this.animateButton($button, originalText);
                } else {
                    this.showStatus(cac_ajax.strings.copy_error, 'error');
                }
            } catch (err) {
                this.showStatus(cac_ajax.strings.copy_error, 'error');
            } finally {
                document.body.removeChild(textArea);
            }
        }
        
        getPostsAsText() {
            const posts = [];
            this.postsContainer.find('.cac-post-row').each(function() {
                const title = $(this).find('.cac-post-title').text().trim();
                const date = $(this).find('.cac-post-date').text().trim();
                if (title && date) {
                    posts.push(`${title} - ${date}`);
                }
            });
            
            return posts.length ? posts.join('\n') : '';
        }
        
        animateButton($button, originalText) {
            $button.text('✓ ' + cac_ajax.strings.copy_success);
            $button.addClass('cac-success');
            
            setTimeout(() => {
                $button.text(originalText);
                $button.removeClass('cac-success');
            }, 2000);
        }
        
        filterPosts() {
            const filters = this.getFilters();
            
            this.showLoading(true);
            
            $.ajax({
                url: cac_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'cac_get_posts',
                    nonce: cac_ajax.nonce,
                    atts: JSON.stringify(this.atts),
                    ...filters
                },
                success: (response) => {
                    if (response.success) {
                        this.postsContainer.html(response.data.html);
                        this.showStatus('', '');
                    } else {
                        this.showStatus('Error loading posts', 'error');
                    }
                },
                error: () => {
                    this.showStatus('Error loading posts', 'error');
                },
                complete: () => {
                    this.showLoading(false);
                }
            });
        }
        
        clearFilters() {
            this.container.find('#cac-date-from').val('');
            this.container.find('#cac-date-to').val('');
            this.container.find('#cac-category').val('');
            this.filterPosts();
        }
        
        getFilters() {
            return {
                date_from: this.container.find('#cac-date-from').val(),
                date_to: this.container.find('#cac-date-to').val(),
                category: this.container.find('#cac-category').val()
            };
        }
        
        toggleExportOptions(button) {
            const $options = this.container.find('.cac-export-options');
            $options.toggle();
            
            // Position the dropdown
            const $button = $(button);
            const buttonPos = $button.position();
            $options.css({
                position: 'absolute',
                top: buttonPos.top + $button.outerHeight() + 5,
                left: buttonPos.left,
                zIndex: 1000
            });
        }
        
        exportPosts(format) {
            const filters = this.getFilters();
            
            this.showLoading(true);
            
            $.ajax({
                url: cac_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'cac_export_posts',
                    nonce: cac_ajax.nonce,
                    atts: JSON.stringify(this.atts),
                    format: format,
                    ...filters
                },
                success: (response) => {
                    if (response.success) {
                        this.downloadFile(
                            response.data.content,
                            response.data.filename,
                            response.data.mime_type
                        );
                        this.showStatus(`Export completed: ${response.data.filename}`, 'success');
                    } else {
                        this.showStatus('Export failed', 'error');
                    }
                },
                error: () => {
                    this.showStatus('Export failed', 'error');
                },
                complete: () => {
                    this.showLoading(false);
                    this.container.find('.cac-export-options').hide();
                }
            });
        }
        
        downloadFile(base64Content, filename, mimeType) {
            // Convert base64 to blob
            const byteCharacters = atob(base64Content);
            const byteNumbers = new Array(byteCharacters.length);
            for (let i = 0; i < byteCharacters.length; i++) {
                byteNumbers[i] = byteCharacters.charCodeAt(i);
            }
            const byteArray = new Uint8Array(byteNumbers);
            const blob = new Blob([byteArray], { type: mimeType });
            
            // Create download link
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            link.style.display = 'none';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Clean up
            window.URL.revokeObjectURL(url);
        }
        
        showLoading(show) {
            if (show) {
                this.loadingIndicator.show();
                this.postsContainer.css('opacity', '0.5');
            } else {
                this.loadingIndicator.hide();
                this.postsContainer.css('opacity', '1');
            }
        }
        
        showStatus(message, type) {
            this.statusElement.removeClass('cac-status-success cac-status-error');
            
            if (type) {
                this.statusElement.addClass(`cac-status-${type}`);
            }
            
            this.statusElement.text(message);
            
            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(() => {
                    this.statusElement.text('').removeClass('cac-status-success');
                }, 3000);
            }
        }
    }
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        $('.content-archive-clipboard').each(function() {
            new ContentArchiveClipboard(this);
        });
    });
    
})(jQuery);