# Magento 2 OPcache GUI

Magento 2 Opcache Control GUI using React Micro-services Frontend. 

![MAgento 2 Opcache GUI](https://github.com/Genaker/Magento2OPcacheGUI/raw/main/Magento-Opcache-Gui.jpg)

# Where to find in the Admin Menu

System -> React -> OpCahe GUI

# Installation 

Copy to App code, Setup, compile as always. 

This Extension don't need static content generation it usses CDN version fo the React JS. So, you can install with flag *--keep-generated*

# Magento 2 Opcache best settings
```
opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 356
opcache.max_accelerated_files = 100000
opcache.validate_timestamps = 0
```
