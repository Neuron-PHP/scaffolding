## 0.9.0
* **All scaffolding commands now use system abstractions** - Refactored to use IFileSystem interface
* Refactored 8 commands: ControllerCommand, EmailCommand, EventCommand, InitializerCommand, JobCommand, ListenerCommand, Queue/InstallCommand, ScaffoldCommand
* All commands support dependency injection with optional `IFileSystem` parameter for testability
* Filesystem operations now use `IFileSystem` abstraction instead of direct PHP functions
* Added dependency on neuron-php/core 0.8.* for IFileSystem, MemoryFileSystem
* **Test coverage dramatically improved from 16.06% to 37.87%** (+21.81 percentage points, +250 lines covered)
* Added 47 new comprehensive tests using MemoryFileSystem for deterministic, fast testing
* All 108 tests passing (0 failures, 3 skipped)
* Coverage by command:
  - EmailCommand: 52.63% (30/57 lines) - Added 6 tests
  - InitializerCommand: 62.96% (34/54 lines) - Added 5 tests
  - EventCommand: 69.77% (60/86 lines) - Added 7 tests
  - ListenerCommand: 60.58% (63/104 lines) - Added 10 tests
  - JobCommand: 65.55% (78/119 lines) - Added 11 tests
  - Queue/InstallCommand: 22.92% (33/144 lines) - Added 6 tests
  - ScaffoldCommand: 26.39% (90/341 lines) - Added 4 tests
  - ControllerCommand: 22.01% (46/209 lines) - Added 4 tests

## 0.8.8

## 0.8.7 2025-12-02
* Added the scaffold command.

## 0.8.6 2025-11-28

## 0.8.5 2025-11-25

## 0.8.4 2025-11-22
* First release.
