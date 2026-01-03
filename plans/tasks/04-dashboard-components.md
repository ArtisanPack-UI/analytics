Feature Name: Dashboard Components
Requested By: Internal
Owned By: TBD

## What is the Feature

Build the analytics dashboard UI including the query service, Livewire widgets for displaying metrics, a full-page dashboard, and page-level analytics for visual editor integration.

## Tasks

### Query Service

- [ ] Create `AnalyticsQuery` service class
- [ ] Implement `getStats()` method with comparison support
- [ ] Implement `getPageViews()` method with grouping
- [ ] Implement `getVisitors()` method with filtering
- [ ] Implement `getSessions()` method
- [ ] Implement `getTopPages()` method
- [ ] Implement `getTrafficSources()` method
- [ ] Implement `getBounceRate()` method
- [ ] Implement `getAverageSessionDuration()` method
- [ ] Implement `getAveragePagesPerSession()` method
- [ ] Implement `getRealtime()` method for live data
- [ ] Add caching for expensive queries
- [ ] Create `DateRange` helper class for period handling

### Livewire Widgets

- [ ] Create `StatsCards` widget (page views, visitors, sessions, bounce rate, etc.)
- [ ] Create `VisitorsChart` widget with Chart.js integration
- [ ] Create `TopPages` widget with sortable list
- [ ] Create `TrafficSources` widget with pie/bar chart
- [ ] Create `RealtimeVisitors` widget with live updates
- [ ] Create `GoalsOverview` widget showing conversion metrics
- [ ] Create base widget class with shared functionality

### Full Dashboard

- [ ] Create `AnalyticsDashboard` Livewire component
- [ ] Implement period selector (24h, 7d, 30d, 90d, 1y)
- [ ] Implement tab navigation (Overview, Pages, Traffic, Events, Goals)
- [ ] Implement export functionality (CSV, JSON)
- [ ] Add responsive layout for mobile/tablet

### Page-Level Analytics

- [ ] Create `PageAnalytics` component for visual editor integration
- [ ] Implement per-page stats display
- [ ] Implement inline chart for views over time
- [ ] Add scroll depth visualization

### Views

- [ ] Create dashboard layout view
- [ ] Create widget partial views
- [ ] Create stats cards partial
- [ ] Create chart container partials
- [ ] Add dark mode support

## Accessibility Notes

- [ ] Ensure all charts have proper ARIA labels
- [ ] Provide text alternatives for chart data
- [ ] Ensure keyboard navigation for interactive elements
- [ ] Use sufficient color contrast for all UI elements

## UX Notes

- Widgets should show loading states
- Use skeleton loaders during data fetch
- Provide empty states with helpful messages
- Charts should be responsive and touch-friendly

## Testing Notes

- [ ] Test AnalyticsQuery service methods
- [ ] Test widget rendering
- [ ] Test period selection
- [ ] Test export functionality
- [ ] Test real-time updates
- [ ] Test responsive layout

## Documentation Notes

- [ ] Document embedding widgets in custom pages
- [ ] Document customizing dashboard layout
- [ ] Document available widget options

## Related Planning Documents

- [05-dashboard-components.md](../05-dashboard-components.md)
