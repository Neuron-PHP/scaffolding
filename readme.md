[![CI](https://github.com/Neuron-PHP/scaffolding/actions/workflows/ci.yml/badge.svg)](https://github.com/Neuron-PHP/scaffolding/actions)
[![codecov](https://codecov.io/gh/Neuron-PHP/scaffolding/branch/develop/graph/badge.svg)](https://codecov.io/gh/Neuron-PHP/scaffolding)
# Neuron Scaffolding

Code generators and scaffolding tools for the Neuron PHP framework.

## Installation

```bash
composer require --dev neuron-php/scaffolding
```

## Available Commands

### Controller Generation
```bash
php neuron controller:generate UserController --type=resource
```

### Event & Listener Generation
```bash
php neuron event:generate UserRegistered --property="userId:int" --property="email:string"
php neuron listener:generate SendWelcomeEmail --event="App\Events\UserRegistered"
```

### Job Generation
```bash
php neuron job:generate SendEmailReminders --cron="0 9 * * *"
```

### Initializer Generation
```bash
php neuron initializer:generate QueueInitializer
```

### Migration Generation
```bash
php neuron db:migration:generate CreateUsersTable
```

### Email Template Generation
```bash
php neuron mail:generate welcome
```

## Learn More

Visit [neuronphp.com](http://neuronphp.com) for full documentation.
