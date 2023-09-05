# Magento 2 OPcache GUI

Magento 2 Opcache Control GUI using React Frontend Micro-services . 

![MAgento 2 Opcache GUI](https://github.com/Hgati/Magento2OPcacheGUI/raw/main/hgati-opcache-gui.jpg)

# Where to find in the Admin Menu

System -> React -> OpCache GUI

# Installation 

Copy to App code, Setup, compile as always. 

This Extension don't need static content generation it usses CDN version fo the React JS. So, you can install with flag *--keep-generated*

or use composer: 
```
composer require hgati/module-opcache
```

# Magento 2 Opcache best settings

The biggest Magento 2 performance issue is wrong (default) PHP OPcache settings. 

Check your PHP settings with this module:
```
opcache.enable = 1
opcache.enable_cli = 0
opcache.jit_buffer_size = 128M
opcache.memory_consumption = 256
opcache.max_accelerated_files = 70000
opcache.validate_timestamps = 0
```
# PHP performance measurement

New feature has been added. Now you will have PHP perofmance test on GUI open. 

Magento 2 is CPU intensive platform due to bad framework design. You shold use the fastes CPU to achive a good page rendering performance. If Magento 2 takes a 2GHz processor core 3 seconds to process a request, then the same request would be returned in around 2 seconds by a 3GHz processor core. Test yor PHP performance. 

![Magento 2 PHP performance](https://github.com/Hgati/Magento2OPcacheGUI/raw/main/PHP-performance.jpg)

AWS C5.large has *0.032* PHP 7.3.23 performance score (less is beter).
AWS R5.xlrge has *0.039* PHP 7.2.34 performance score (less is beter).
