<p align="center"> <a href="https://omega-mvc.github.io" target="_blank">
        <img src="https://github.com/omega-mvc/omega-assets/blob/main/images/logo-omega.png" alt="Omega Logo">
    </a>
</p>

<p align="center">
    <a href="https://omega-mvc.github.io">Documentation</a> |
    <a href="https://github.com/omega-mvc/omega-kernel/blob/main/CHANGELOG.md">Changelog</a> |
    <a href="https://github.com/omega-mvc/omega-kernel/blob/main/CONTRIBUTING.md">Contributing</a> |
    <a href="https://github.com/omega-mvc/omega-kernel/blob/main/CODE_OF_CONDUCT.md">Code of Conduct</a> |
    <a href="https://github.com/omega-mvc/omega-kernel/blob/main/LICENSE">License</a>
</p>

## Overview

Omega Kernel is the lowest-level package of the Omega architecture.

Its purpose is to provide a minimal and stable runtime layer shared by higher-level packages such as Omega Framework and Omega WordPress Framework.

The kernel does not provide application features, MVC components, database abstractions, routing, views, or platform-specific integrations. Those responsibilities belong to higher-level packages.

## Responsibilities

Omega Kernel is responsible for:

- Application lifecycle management
- Service provider registration and bootstrapping
- Dependency container foundation
- Core runtime abstractions
- Kernel-level contracts and interfaces
- Common exceptions and utilities required during application startup

## Architecture

The Omega ecosystem is divided into different layers:

```
+-------------------------+
|      Omega App          |
+-------------------------+
            |
            v
+-------------------------+
|   Omega Framework       |
|   MVC / Application     |
+-------------------------+ 
            |
            v
+-------------------------+
|     Omega Kernel        |
| Runtime / Container     |
+-------------------------+
```

## Official Documentation

The official documentation for Omega is available [here](https://omega-mvc.github.io)

## Contributing

If you'd like to contribute to the Omega example application package, please follow our [contribution guidelines](CONTRIBUTING.md).

## License

This project is open-source software licensed under the [GNU General Public License v3.0](LICENSE).