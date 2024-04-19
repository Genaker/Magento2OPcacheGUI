# Magento 2 OPcache GUI PHP Performance Dashboard

Magento 2 Opcache Control GUI using React Frontend Micro-services. 

![MAgento 2 Opcache GUI](https://github.com/Genaker/Magento2OPcacheGUI/raw/main/Magento-Opcache-Gui.jpg)

# Where to find in the Admin Menu

System -> React -> OpCache GUI

# Installation 

Copy to App code, Setup, and compile as always. 

This Extension doesn't need static content generation it uses CDN version of React JS. So, you can install with flag *--keep-generated*

or use composer: 
```
composer require genaker/module-opcache
```

# Magento 2 Opcache best settings

The biggest Magento 2 performance issue is the wrong (default) PHP OPcache settings. 

Check your PHP settings with this module:
```
opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 556
opcache.max_accelerated_files = 1000000
opcache.validate_timestamps = 0
opcache.interned_strings_buffer=64
opcache.max_wasted_percentage=5
opcache.save_comments=1
opcache.fast_shutdown=1
```
# PHP performance measurement

New feature has been added. Now you will have PHP performance test on GUI open. 

Magento 2 is CPU CPU-intensive platform due to bad framework design. You should use the fastest CPU to achieve a good page rendering performance. If Magento 2 takes a 2GHz processor core 3 seconds to process a request, then the same request would be returned in around 2 seconds by a 3GHz processor core. Test your PHP performance. 

![Magento 2 PHP performance](https://github.com/Genaker/Magento2OPcacheGUI/raw/main/PHP-performance.jpg)

AWS C5.large has *0.032* PHP 7.3.23 performance score (less is better).
AWS R5.xlrge has *0.039* PHP 7.2.34 performance score (less is better).
