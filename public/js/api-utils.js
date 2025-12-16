/**
 * API Utility Functions for Optimized Requests
 * Provides debouncing, request cancellation, and duplicate prevention
 */

class ApiUtils {
    constructor() {
        this.activeRequests = new Map();
        this.debounceTimers = new Map();
    }

    /**
     * Create an optimized fetch request with cancellation support
     */
    async fetch(url, options = {}) {
        const requestKey = `${options.method || 'GET'}_${url}`;
        
        // Cancel previous request if exists
        if (this.activeRequests.has(requestKey)) {
            this.activeRequests.get(requestKey).abort();
        }

        // Create new AbortController
        const controller = new AbortController();
        this.activeRequests.set(requestKey, controller);

        try {
            const response = await fetch(url, {
                ...options,
                signal: controller.signal
            });

            // Remove from active requests on success
            this.activeRequests.delete(requestKey);
            
            return response;
        } catch (error) {
            // Remove from active requests on error
            this.activeRequests.delete(requestKey);
            
            // Don't throw if request was aborted
            if (error && error.name === 'AbortError') {
                return null;
            }

            // For network errors (offline, DNS, etc.) or other fetch failures
            // return null instead of throwing so callers can handle gracefully.
            console.error('ApiUtils.fetch error for', url, error);
            return null;
        }
    }

    /**
     * Debounced function execution
     */
    debounce(key, fn, delay = 300) {
        // Clear existing timer
        if (this.debounceTimers.has(key)) {
            clearTimeout(this.debounceTimers.get(key));
        }

        // Set new timer
        const timer = setTimeout(() => {
            fn();
            this.debounceTimers.delete(key);
        }, delay);

        this.debounceTimers.set(key, timer);
    }

    /**
     * Cancel all active requests
     */
    cancelAll() {
        this.activeRequests.forEach(controller => controller.abort());
        this.activeRequests.clear();
    }

    /**
     * Clear all debounce timers
     */
    clearDebounces() {
        this.debounceTimers.forEach(timer => clearTimeout(timer));
        this.debounceTimers.clear();
    }

    /**
     * Cleanup all resources
     */
    cleanup() {
        this.cancelAll();
        this.clearDebounces();
    }
}

// Global instance
window.apiUtils = new ApiUtils();

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    window.apiUtils.cleanup();
});

