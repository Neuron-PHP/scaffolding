## 0.8.10

## 0.8.9 2026-06-05
* Massive upgrade. Supports Dtos, Repositories, and Services. Added 94 new tests with MemoryFileSystem for 62.74% coverage. Refactored all scaffolding commands to use IFileSystem abstraction for testability and maintainability.
* Can now generate Dtos from a database table.


## 0.8.8 2026-01-13

* **All scaffolding commands now use system abstractions** - Refactored to use IFileSystem interface
* Refactored 8 commands: ControllerCommand, EmailCommand, EventCommand, InitializerCommand, JobCommand, ListenerCommand, Queue/InstallCommand, ScaffoldCommand
* All commands support dependency injection with optional `IFileSystem` parameter for testability
* Filesystem operations now use `IFileSystem` abstraction instead of direct PHP functions
* Added dependency on neuron-php/core 0.8.* for IFileSystem, MemoryFileSystem
* **Test coverage dramatically improved from 16.06% to 62.74%** (+46.68 percentage points, +535 lines covered)
* Added 94 new comprehensive tests using MemoryFileSystem for deterministic, fast testing
* All 150 tests passing (0 failures, 3 skipped, 364 assertions)
* Coverage by command (final results):
    - Provider: 100.00% (32/32 lines) - 10 tests (NEW - complete coverage)
    - ControllerCommand: 76.56% (160/209 lines) - 31 tests (+27 tests, +54.55% coverage)
    - EventCommand: 69.77% (60/86 lines) - 7 tests
    - ScaffoldCommand: 67.16% (229/341 lines) - 38 tests (+34 tests, +40.77% coverage)
    - JobCommand: 65.55% (78/119 lines) - 11 tests
    - InitializerCommand: 62.96% (34/54 lines) - 5 tests
    - ListenerCommand: 60.58% (63/104 lines) - 10 tests
    - EmailCommand: 52.63% (30/57 lines) - 6 tests
    - Queue/InstallCommand: 22.92% (33/144 lines) - 18 tests (limited by CLI integration dependencies)


## 0.8.7 2025-12-02
* Added the scaffold command.

## 0.8.6 2025-11-28

## 0.8.5 2025-11-25

## 0.8.4 2025-11-22
* First release.
