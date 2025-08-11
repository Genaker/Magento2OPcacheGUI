# Magento 2 OPcache GUI PHP Performance Dashboard

Advanced Magento 2 Performance Monitoring & OPcache Control GUI with comprehensive benchmarking tools.

![Magento 2 Opcache GUI](https://github.com/Genaker/Magento2OPcacheGUI/raw/main/Magento-Opcache-Gui.jpg)

# Magento Server Performance Toolkit New GUI

![opcacheGui](https://github.com/user-attachments/assets/dbe40aea-cf95-43ed-be34-a3a6b451bf08)

## Key Features

### **Performance Benchmarking Suite**
- **CPU Performance Testing** - Multi-iteration BogoMIPS measurement
- **Memory Allocation Analysis** - Memory usage patterns and optimization detection
- **File Operations Testing** - I/O performance measurement
- **Database Latency Testing** - MySQL connection and query performance
- **Redis Latency Testing** - Cache backend performance analysis
- **HTTP Performance Testing** - Cached vs Uncached page load comparison
- **Random URL Testing** - Real-world product and category page performance

### **Advanced HTTP Performance Analysis**
- **Cached Performance Testing** - Measures Full Page Cache (FPC) effectiveness
- **Uncached Performance Testing** - Real-world logged-in user experience
- **Random Product URL Testing** - Dynamic product page performance
- **Random Category URL Testing** - Category browsing performance
- **Cache Performance Comparison** - Side-by-side cached vs uncached analysis
- **Cache Limitations Warning** - Educational information about FPC behavior

### **Enhanced User Interface**
- **Loading Spinner with Dynamic Steps** - Rotating performance test indicators
- **Console-themed Design** - Professional terminal-style interface
- **Real-time Progress Tracking** - Step-by-step loading feedback
- **Responsive Layout** - Optimized for admin panel integration
- **Professional Error Handling** - Graceful fallbacks and debugging

### **Configuration & Diagnostics**
- **OPcache Status Analysis** - Comprehensive OPcache configuration review
- **PHP Configuration Analysis** - PHP settings optimization recommendations
- **Security Analysis** - Security configuration assessment
- **Server Configuration Review** - System-level performance insights
- **Configurable Test Parameters** - Customizable iterations and timeouts via DI

### **Technical Architecture**
- **Dependency Injection** - Clean, testable architecture
- **Performance Toolkit Class** - Modular performance testing functions
- **Block-based Architecture** - Magento 2 best practices
- **Random URL Generation** - Store-aware product and category selection
- **Error Handling & Logging** - Comprehensive error management
- **Cache-busting Technology** - Accurate uncached performance measurement

## Where to Find in Admin Menu

**System > React > OpCache GUI**

## Installation 

### Method 1: Manual Installation
Copy to `app/code`, run setup, and compile as usual.

This Extension doesn't need static content generation - it uses CDN version of React JS. Install with flag `--keep-generated`.

### Method 2: Composer Installation
```bash
composer require genaker/module-opcache
```

### Post-Installation
```bash
php bin/magento module:enable Genaker_Opcache
php bin/magento setup:upgrade
php bin/magento cache:flush
```

## Magento 2 OPcache Optimal Settings

The biggest Magento 2 performance issue is incorrect (default) PHP OPcache settings.

### Production Web Server Settings
```ini
opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 556
opcache.max_accelerated_files = 1000000
opcache.validate_timestamps = 0
opcache.interned_strings_buffer = 64
opcache.max_wasted_percentage = 5
opcache.save_comments = 1
opcache.fast_shutdown = 1
```

### CLI OPcache Settings
Separate CLI config file (e.g., `/etc/php/8.1/cli/conf.d/10-opcache.ini`):
```ini
zend_extension=opcache.so
opcache.memory_consumption=1000M
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000000
opcache.validate_timestamps=1
opcache.enable_cli=1
opcache.file_cache=/tmp/
opcache.file_cache_only=0
opcache.file_cache_consistency_checks=1
```

## Performance Benchmarking Features

### **PHP BogoMIPS Performance Measurement**
Advanced PHP performance testing that measures actual Magento code execution speed.

Magento 2 is CPU-intensive due to framework design. Use the fastest CPU for optimal page rendering. If a 2GHz processor takes 3 seconds, a 3GHz processor will complete the same request in ~2 seconds.

![Magento 2 PHP performance](https://github.com/Genaker/Magento2OPcacheGUI/raw/main/PHP-performance.jpg)

#### Benchmark Reference Scores (lower is better):
- **AWS C5.large**: 0.032 (PHP 7.3.23)
- **AWS R5.xlarge**: 0.039 (PHP 7.2.34)  
- **AWS C8.xlarge**: 0.029 (PHP 8.1 web), 0.066 (CLI - OPcache limitation)

### **HTTP Performance Testing**
- **Cached Testing**: Measures Full Page Cache effectiveness
- **Uncached Testing**: Real logged-in user experience with cache-busting
- **Random URL Testing**: Dynamic product/category page performance
- **Performance Comparison**: Side-by-side analysis with improvement percentages

### **Cache Limitations Analysis**
Educational warnings about Magento FPC behavior:
- FPC primarily benefits guest visitors only
- Logged-in customers typically bypass FPC
- Cache can be invalidated by content updates
- Search requests and category filters are rarely cached
- Focus on uncached performance for sustainable improvements

## **Advanced Configuration**

### Dependency Injection Configuration
Performance parameters can be customized via `etc/di.xml`:

```xml
<argument name="config" xsi:type="array">
    <item name="performance_iterations" xsi:type="number">3</item>
    <item name="http_performance_iterations" xsi:type="number">3</item>
    <item name="db_performance_iterations" xsi:type="number">3</item>
    <item name="collection_page_size" xsi:type="number">100</item>
</argument>
```

### Performance Test Categories
- **[PERFORMANCE BENCHMARK]** - CPU, Memory, File I/O tests
- **[HTTP PERFORMANCE]** - Cached page performance
- **[UNCACHED HTTP PERFORMANCE]** - Real-world performance
- **[CACHE PERFORMANCE COMPARISON]** - Cached vs Uncached analysis
- **[OPCACHE STATUS]** - OPcache configuration analysis
- **[PHP CONFIGURATION ANALYSIS]** - PHP settings review
- **[SECURITY ANALYSIS]** - Security configuration assessment

## **What is BogoMIPS for Magento Servers?**

**MIPS** = Millions of Instructions Per Second - measures Magento server computation speed.

**BogoMips** are Linus Torvalds' invention, adapted for Magento servers by Yehor Shytikov. Originally used in Linux kernel 0.99.11 (July 1993) for timing loop calibration.

"Bogo" = "bogus" (fake), indicating this is a practical rather than scientific measurement.

It's the most effective way to measure and compare Magento PHP code execution performance across different servers.

## **Technical Implementation**

### Class Architecture
- **`Gui` Block**: Main interface and URL generation
- **`PerformanceToolkit`**: Modular performance testing functions
- **Random URL Generation**: Store-aware product/category selection
- **Dependency Injection**: Clean, configurable architecture

### Browser Features
- **Dynamic Loading Spinner**: Rotating test step indicators
- **Automatic Fallbacks**: 10-second timeout protection
- **Error Handling**: Comprehensive debugging and logging
- **Responsive Design**: Admin panel optimized interface

---

**Optimize your Magento 2 performance with comprehensive, real-world testing tools.**
