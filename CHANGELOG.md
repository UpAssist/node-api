# Changelog

All notable changes to this project will be documented in this file. See [standard-version](https://github.com/conventional-changelog/standard-version) for commit guidelines.

### [8.0.2](https://github.com/UpAssist/node-api/compare/8.0.0...8.0.2) (2026-11-19)


### Bug Fixes

* **security:** only import real HTTP uploads in the write service. 8.0.1 made `$_FILES`-style arrays in node data work again, which let request data point `tmp_name` at any file on the server. Arrays now need `is_uploaded_file()`; PSR-7 uploaded files are accepted as well. Upgrade from 8.0.1 right away.

### 8.0.1 (2026-11-19)

**Withdrawn.** This release turned `$_FILES`-style arrays in node data back on without checking that they are real uploads, so request data could import any server file as an asset. The tag has been removed; use 8.0.2.


### Bug Fixes

* require neos ^8.3 explicitly instead of neos/flow * (Neos 9 is not supported)
* map uploaded files to assets and images again
* register the nodeIdentifier viewhelper argument

## [8.0.0](https://bitbucket.org/upassist/upassist-node-api/compare/4.0.1...8.0.0) (2022-10-17)
