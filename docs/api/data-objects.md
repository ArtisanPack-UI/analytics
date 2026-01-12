---
title: Data Objects
---

# Data Objects

ArtisanPack UI Analytics uses Data Transfer Objects (DTOs) to pass data between components.

## DateRange

Represents a date range for queries.

### Creating Instances

```php
use ArtisanPackUI\Analytics\Data\DateRange;

// Preset ranges
$range = DateRange::today();
$range = DateRange::yesterday();
$range = DateRange::thisWeek();
$range = DateRange::lastWeek();
$range = DateRange::thisMonth();
$range = DateRange::lastMonth();
$range = DateRange::thisYear();
$range = DateRange::last7Days();
$range = DateRange::last30Days();
$range = DateRange::last90Days();
$range = DateRange::lastDays(14);

// From strings
$range = DateRange::fromStrings('2024-01-01', '2024-01-31');

// From Carbon instances
$range = DateRange::fromCarbon($startCarbon, $endCarbon);

// Manual construction
$range = new DateRange($startDate, $endDate);
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `startDate` | CarbonInterface | Start of range |
| `endDate` | CarbonInterface | End of range |

### Methods

```php
// Get number of days
$days = $range->getDays();

// Get previous period for comparison
$previous = $range->getPreviousPeriod();
// Alias
$previous = $range->previousPeriod();

// Get cache key
$key = $range->toKey(); // "2024-01-01_2024-01-31"

// Convert to array
$array = $range->toArray();
// ['start_date' => '...', 'end_date' => '...']
```

---

## PageViewData

Data for tracking a page view.

### Creating Instances

```php
use ArtisanPackUI\Analytics\Data\PageViewData;

$data = new PageViewData(
    path: '/products/widget',
    title: 'Widget Product Page',
    referrer: 'https://google.com',
    customData: [
        'product_id' => 123,
        'category' => 'widgets',
    ],
);
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `path` | string | Page URL path (required) |
| `title` | ?string | Page title |
| `referrer` | ?string | Referrer URL |
| `customData` | ?array | Additional custom data |

### Usage

```php
use ArtisanPackUI\Analytics\Facades\Analytics;

Analytics::trackPageView($data);

// Or with helper
trackPageView($data->path, $data->title, $data->customData);
```

---

## EventData

Data for tracking a custom event.

### Creating Instances

```php
use ArtisanPackUI\Analytics\Data\EventData;

$data = new EventData(
    name: 'purchase',
    properties: [
        'product_id' => 123,
        'product_name' => 'Widget',
        'quantity' => 2,
    ],
    value: 49.99,
    category: 'ecommerce',
    sourcePackage: 'my-package', // Optional: track which package triggered the event
);
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `name` | string | Event name (required) |
| `properties` | ?array | Event properties |
| `value` | ?float | Numeric value |
| `category` | ?string | Event category |
| `sourcePackage` | ?string | Originating package |

### Usage

```php
use ArtisanPackUI\Analytics\Facades\Analytics;

Analytics::trackEvent($data);

// Or with helper
trackEvent($data->name, $data->properties, $data->value, $data->category);
```

---

## SessionData

Data for creating a session.

### Creating Instances

```php
use ArtisanPackUI\Analytics\Data\SessionData;

$data = new SessionData(
    visitorId: 'visitor-fingerprint',
    referrer: 'https://google.com',
    utmSource: 'newsletter',
    utmMedium: 'email',
    utmCampaign: 'summer_sale',
    entryPage: '/products',
    deviceInfo: $deviceInfo,
);
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `visitorId` | string | Visitor fingerprint |
| `referrer` | ?string | Traffic referrer |
| `utmSource` | ?string | UTM source |
| `utmMedium` | ?string | UTM medium |
| `utmCampaign` | ?string | UTM campaign |
| `utmTerm` | ?string | UTM term |
| `utmContent` | ?string | UTM content |
| `entryPage` | ?string | First page viewed |
| `deviceInfo` | ?DeviceInfo | Device information |

---

## VisitorData

Data for identifying a visitor.

### Creating Instances

```php
use ArtisanPackUI\Analytics\Data\VisitorData;

$data = new VisitorData(
    fingerprint: 'unique-visitor-hash',
    ipAddress: '192.168.1.1',
    userAgent: 'Mozilla/5.0...',
    country: 'US',
    city: 'New York',
    region: 'NY',
);
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `fingerprint` | string | Unique visitor identifier |
| `ipAddress` | ?string | IP address |
| `userAgent` | ?string | User agent string |
| `country` | ?string | Country code |
| `city` | ?string | City name |
| `region` | ?string | Region/state |

---

## DeviceInfo

Information about a visitor's device.

### Creating Instances

```php
use ArtisanPackUI\Analytics\Data\DeviceInfo;

$data = new DeviceInfo(
    deviceType: 'desktop',
    browser: 'Chrome',
    browserVersion: '120.0',
    os: 'Windows',
    osVersion: '11',
    screenResolution: '1920x1080',
);
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `deviceType` | string | desktop/mobile/tablet |
| `browser` | ?string | Browser name |
| `browserVersion` | ?string | Browser version |
| `os` | ?string | Operating system |
| `osVersion` | ?string | OS version |
| `screenResolution` | ?string | Screen dimensions |

### Static Methods

```php
// Create from user agent string
$deviceInfo = DeviceInfo::fromUserAgent($userAgentString);

// Create from request
$deviceInfo = DeviceInfo::fromRequest($request);
```

---

## Using Data Objects with Validation

Data objects can be validated before use:

```php
use Illuminate\Support\Facades\Validator;

$rules = [
    'path' => 'required|string|max:2048',
    'title' => 'nullable|string|max:255',
];

$validator = Validator::make([
    'path' => $data->path,
    'title' => $data->title,
], $rules);

if ($validator->fails()) {
    // Handle validation errors
}
```

## Converting to Array

All data objects can be converted to arrays:

```php
$array = $pageViewData->toArray();
$array = $eventData->toArray();
$array = $dateRange->toArray();
```

## Immutability

Data objects are readonly and immutable. To modify, create a new instance:

```php
// Create modified copy
$newData = new PageViewData(
    path: $oldData->path,
    title: 'New Title', // Modified
    referrer: $oldData->referrer,
    customData: $oldData->customData,
);
```
