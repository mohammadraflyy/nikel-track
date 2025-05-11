<?php

use Livewire\Volt\Component;
use App\Models\Booking;
use App\Models\UsageLog;
use function Livewire\Volt\{state};

new class extends Component {
    public array $chartData = [];
    public array $usageChartData = [];

    public function mount()
    {
        $this->chartData = $this->getChartData();
        $this->usageChartData = $this->getUsageChartData();
    }

    protected function getBookingData()
    {
        return Booking::query()
            ->selectRaw('YEAR(start_date) as year, MONTH(start_date) as month, COUNT(*) as booking_count')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();
    }

    protected function getUsageData()
    {
        return UsageLog::query()
            ->join('bookings', 'usage_logs.booking_id', '=', 'bookings.id')
            ->selectRaw('YEAR(bookings.start_date) as year, MONTH(bookings.start_date) as month, 
                         SUM(usage_logs.end_km - usage_logs.start_km) as total_km,
                         SUM(usage_logs.fuel_used) as total_fuel')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();
    }

    protected function getChartData(): array
    {
        $bookings = $this->getBookingData();
        
        return [
            'labels' => $bookings->map(fn ($item) => 
                date('M Y', mktime(0, 0, 0, $item->month, 1, $item->year))
            ),
            'datasets' => [[
                'label' => 'Monthly Bookings',
                'data' => $bookings->pluck('booking_count'),
                'backgroundColor' => '#6366f1',
                'borderColor' => '#4f46e5',
                'borderWidth' => 1,
                'borderRadius' => 4
            ]]
        ];
    }

    protected function getUsageChartData(): array
    {
        $usageData = $this->getUsageData();
        
        return [
            'labels' => $usageData->map(fn ($item) => 
                date('M Y', mktime(0, 0, 0, $item->month, 1, $item->year))
            ),
            'datasets' => [
                [
                    'label' => 'Total Kilometers',
                    'data' => $usageData->pluck('total_km'),
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#059669',
                    'borderWidth' => 1,
                    'borderRadius' => 4
                ],
                [
                    'label' => 'Total Fuel Used (L)',
                    'data' => $usageData->pluck('total_fuel'),
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#d97706',
                    'borderWidth' => 1,
                    'borderRadius' => 4
                ]
            ]
        ];
    }
};

state(['chartData' => [], 'usageChartData' => []]);

?>

<div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 overflow-hidden">
    <!-- Header -->
    <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-700">
        <h3 class="text-lg font-medium text-zinc-900 dark:text-white flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" viewBox="0 0 20 20" fill="currentColor">
                <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
            </svg>
            Monthly Bookings Overview
        </h3>
    </div>

    <!-- Chart Container -->
    <div class="p-4">
        <div x-data="{
            chart: null,
            isDark: window.matchMedia('(prefers-color-scheme: dark)').matches,
            init() {
                this.initChart();
                
                // Watch for dark mode changes
                window.matchMedia('(prefers-color-scheme: dark)')
                    .addEventListener('change', e => {
                        this.isDark = e.matches;
                        this.updateChartColors();
                    });
            },
            initChart() {
                const ctx = this.$el.querySelector('#bookingChart');
                this.chart = new Chart(ctx, {
                    type: 'bar',
                    data: @js($this->chartData),
                    options: this.getChartOptions()
                });
            },
            updateChartColors() {
                if (!this.chart) return;
                this.chart.options = this.getChartOptions();
                this.chart.update();
            },
            getChartOptions() {
                return {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: this.isDark ? '#1e293b' : '#ffffff',
                            titleColor: this.isDark ? '#f8fafc' : '#1f2937',
                            bodyColor: this.isDark ? '#e2e8f0' : '#4b5563',
                            borderColor: this.isDark ? '#334155' : '#e5e7eb',
                            borderWidth: 1,
                            padding: 12
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: this.isDark ? '#374151' : '#e5e7eb',
                                drawBorder: false
                            },
                            ticks: {
                                color: this.isDark ? '#9ca3af' : '#6b7280'
                            }
                        },
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                color: this.isDark ? '#9ca3af' : '#6b7280'
                            }
                        }
                    }
                };
            }
        }" x-init="init()" @dark-mode-toggled.window="updateChartColors()" wire:ignore class="h-80">
            <canvas id="bookingChart"></canvas>
        </div>
    </div>

    <!-- Header -->
    <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-700">
        <h3 class="text-lg font-medium text-zinc-900 dark:text-white flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-500" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
            </svg>
            Monthly Usage Statistics
        </h3>
    </div>

    <!-- Chart Container -->
    <div class="p-4">
        <div x-data="{
            chart: null,
            isDark: window.matchMedia('(prefers-color-scheme: dark)').matches,
            init() {
                this.initChart();
                
                // Watch for dark mode changes
                window.matchMedia('(prefers-color-scheme: dark)')
                    .addEventListener('change', e => {
                        this.isDark = e.matches;
                        this.updateChartColors();
                    });
            },
            initChart() {
                const ctx = this.$el.querySelector('#usageChart');
                this.chart = new Chart(ctx, {
                    type: 'bar',
                    data: @js($this->usageChartData),
                    options: this.getChartOptions()
                });
            },
            updateChartColors() {
                if (!this.chart) return;
                this.chart.options = this.getChartOptions();
                this.chart.update();
            },
            getChartOptions() {
                return {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { 
                            display: true,
                            position: 'top',
                            labels: {
                                color: this.isDark ? '#e2e8f0' : '#4b5563',
                                boxWidth: 12,
                                padding: 20
                            }
                        },
                        tooltip: {
                            backgroundColor: this.isDark ? '#1e293b' : '#ffffff',
                            titleColor: this.isDark ? '#f8fafc' : '#1f2937',
                            bodyColor: this.isDark ? '#e2e8f0' : '#4b5563',
                            borderColor: this.isDark ? '#334155' : '#e5e7eb',
                            borderWidth: 1,
                            padding: 12
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: this.isDark ? '#374151' : '#e5e7eb',
                                drawBorder: false
                            },
                            ticks: {
                                color: this.isDark ? '#9ca3af' : '#6b7280'
                            }
                        },
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                color: this.isDark ? '#9ca3af' : '#6b7280'
                            }
                        }
                    }
                };
            }
        }" x-init="init()" @dark-mode-toggled.window="updateChartColors()" wire:ignore class="h-80">
            <canvas id="usageChart"></canvas>
        </div>
    </div>
</div>