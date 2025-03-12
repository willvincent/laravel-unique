# Changelog

All notable changes to `laravel-unique` will be documented in this file.

## 1.0 - 2025-03-12

### Initial release 🎉

All basic functionality is in place and fully tested.

You can enforce uniqueness of a model field within the context of other field values, for example to ensure that within a given team or client context (determined by the team_id or client_id field) every "project" is uniquely named by appending a suffix in the case of duplicate values.

The suffix pattern is configurable, or can use a totally custom generator.
