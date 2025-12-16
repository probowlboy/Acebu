/**
 * Medical Dashboard Utilities
 * Calendar, Charts, and Dashboard Helpers
 */

(function() {
    'use strict';

    // Calendar Component Helper
    window.MedicalCalendar = {
        init(element, selectedDate = null) {
            const today = new Date();
            let currentMonth = today.getMonth();
            let currentYear = today.getFullYear();
            
            const weekDays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
            
            function getDaysInMonth(month, year) {
                return new Date(year, month + 1, 0).getDate();
            }
            
            function getFirstDayOfMonth(month, year) {
                return new Date(year, month, 1).getDay();
            }
            
            function generateCalendar() {
                const daysInMonth = getDaysInMonth(currentMonth, currentYear);
                const firstDay = getFirstDayOfMonth(currentMonth, currentYear);
                const days = [];
                
                // Empty cells for days before month starts
                for (let i = 0; i < firstDay; i++) {
                    days.push(null);
                }
                
                // Days of the month
                for (let i = 1; i <= daysInMonth; i++) {
                    days.push(i);
                }
                
                return days;
            }
            
            function getMonthYear() {
                const monthNames = ['JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE',
                                  'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER'];
                return `${monthNames[currentMonth]} ${currentYear}`;
            }
            
            return {
                weekDays,
                calendarDays: generateCalendar(),
                currentMonthYear: getMonthYear(),
                selectedDate: selectedDate || today.getDate(),
                
                previousMonth() {
                    currentMonth--;
                    if (currentMonth < 0) {
                        currentMonth = 11;
                        currentYear--;
                    }
                    this.calendarDays = generateCalendar();
                    this.currentMonthYear = getMonthYear();
                },
                
                nextMonth() {
                    currentMonth++;
                    if (currentMonth > 11) {
                        currentMonth = 0;
                        currentYear++;
                    }
                    this.calendarDays = generateCalendar();
                    this.currentMonthYear = getMonthYear();
                },
                
                selectDate(day) {
                    if (day !== null) {
                        this.selectedDate = day;
                    }
                }
            };
        }
    };

    // Chart Helper
    window.MedicalCharts = {
        createBarChart(canvasId, labels, newData, oldData) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return null;
            
            return new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'New',
                            data: newData,
                            backgroundColor: '#14b8a6',
                            borderRadius: 8
                        },
                        {
                            label: 'Old',
                            data: oldData,
                            backgroundColor: '#ef4444',
                            borderRadius: 8
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 15
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f0f0f0'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        },
        
        createLineChart(canvasId, labels, appointmentData, cancelledData) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return null;
            
            return new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Appointment',
                            data: appointmentData,
                            borderColor: '#14b8a6',
                            backgroundColor: 'rgba(20, 184, 166, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Cancelled',
                            data: cancelledData,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 15
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f0f0f0'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
    };

    // Initialize icons when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) lucide.createIcons();
        });
    } else {
        if (window.lucide) lucide.createIcons();
    }
})();

