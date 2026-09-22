
<h3 align="center">Simple <strong>Admin</strong> Generation</h3>

<p align="center">⛵<code>simple-admin-generator</code> is a powerful and easy-to-use package for generating admin functionalities in your Laravel application. With a focus on simplicity and efficiency, this package streamlines the creation and management of admin panels, making it easier for developers to build robust backend systems.</p>
<p align="center">


<a href="https://packagist.org/packages/hoangphamdev/simple-admin-generator">
    <img src="https://img.shields.io/badge/vesion-V1.0.4-blue" alt="Packagist">
</a>
<a href="https://packagist.org/packages/hoangphamdev/simple-admin-generator">
    <img src="https://img.shields.io/badge/license-MIT-green" alt="Packagist">
</a>

Requirements
------------
 - PHP >= 8.1
 - Laravel >= 9.x

## Prerequisites

If you don't already have an Apache local environment with PHP and MySQL, use one of the following links:

- Windows: https://updivision.com/blog/post/beginner-s-guide-to-setting-up-your-local-development-environment-on-windows
- Linux: https://howtoubuntu.org/how-to-install-lamp-on-ubuntu
- Mac: https://wpshout.com/quick-guides/how-to-install-mamp-on-your-mac/

Also, you will need to install Composer: https://getcomposer.org/doc/00-intro.md   
And Laravel: https://laravel.com/docs/9.x/installation

Make sure all database connections are set up correctly in your .env file.

Installation
------------

Install package using command:
```
composer require hoangphamdev/simple-admin-generator
```


Run following command to install.
```
php artisan sag:install
```

Publish the package configuration:
```
php artisan vendor:publish --tag=sag-config
```

This creates `config/sag.php`, where you can customize values such as `page_name`.

Seed the default administrator account:
```
php artisan sag:seed-admin
```

Seed the default admin menus:
```
php artisan migrate
php artisan sag:seed-menu
```

The menu management screen is available at `/{sag.prefix}/menus` (by default, `/admin/menus`).

Open `http://localhost/admin/login` in browser,use email `admin@sag.com` and password `secret` to login.

Edit your dashboard at `resources/views/sag/dashboard.blade.php`

Documentation
------------

### Generate new UI
This feature will generate a basic UI for you so that you can quickly create your own CRUD functionality.

Run following command:
```
php artisan sag:generate_ui <Your functionnalities name>
```
Example: `php artisan sag:generate_ui Blog`

Then open `http://localhost/admin/blog` to see your new UI.
Your functionality files will be generated following the structure below. Open and edit them as you wish.
#### File Structure
```
┣ 📂Http
   ┗📂Controllers
     ┗📂SAG
       ┗📜BlogController.php
┣ 📂recources
   ┗ 📂views
      ┗ 📂sag
         ┗ 📂blog
            ┣📜create.blade.php
            ┣📜edit.blade.php
            ┣📜edit.blade.php
            ┣📜index.blade.php
```

### Generate CRUD from a model or table

Generate a complete controller, routes, views, and (for a table source) an
Eloquent model. The generated form selects `x-sag-form.*` controls from the
database column types and supports list, search, create, edit, update, and
delete operations.

```bash
php artisan sag:generate_crud --model=Employee
php artisan sag:generate_crud --table=employees
php artisan sag:generate_crud --table=employees --route-name=staff-members --controller=StaffMemberController --model-class=App\Models\StaffMember
```

Use exactly one of `--model` or `--table`. Optional `--route-name` must be
kebab-case; `--controller` must be a StudlyCase name ending in `Controller`;
and `--model-class` is available only with `--table`. Existing generated files
are protected unless `--force` is passed. Add `--skip-menu` to omit the admin
menu item.

### Form components

The package includes server-rendered Bootstrap/AdminLTE form components. They
work immediately after installing the package:

```blade
<x-sag-form.input name="email" label="Email" type="email" :value="$user->email ?? null" required />
<x-sag-form.select name="role_id" label="Role" :options="$roles" placeholder="-- Choose a role --" />
<x-sag-form.multi-select name="role_ids" label="Roles" :options="$roles" :selected="$user->roles->pluck('id')" />
<x-sag-form.checkbox name="active" label="Active" :checked="$user->active" />
<x-sag-form.file name="avatar" label="Avatar" accept="image/*" />
<x-sag-form.datetime name="published_at" label="Published at" :value="$post->published_at?->format('Y-m-d H:i')" />
```

Available controls are `field`, `input`, `textarea`, `select`, `multi-select`,
`checkbox`, `radio`, `file`, and `datetime`. Controls use Laravel `old()` input
before their provided value, render the first validation error, and preserve
custom HTML attributes. Options must be a value-to-label array or Collection;
optgroups and object mappings are not supported in this phase. File upload
forms must use `enctype="multipart/form-data"`.

`datetime` lazily loads bundled Flatpickr assets and submits `Y-m-d H:i`
(`Y-m-d H:i:S` when `enable-seconds` is set). Assets are installed by
`php artisan sag:install`; without that installer publish them with:

```bash
php artisan vendor:publish --tag=assets --force
```

To customize component markup, publish the views and edit the corresponding
file under `resources/views/components/sag-form`:

```bash
php artisan vendor:publish --tag=sag-form-components
```

Other
------------
`simple-admin-generator` based on following plugins or services:

+ [Laravel](https://laravel.com/)
+ [AdminLTE](https://adminlte.io/)
+ [font-awesome](http://fontawesome.io)
+ [toastr](http://codeseven.github.io/toastr/)
+ [sweetalert2](https://github.com/sweetalert2/sweetalert2)

License
------------
`simple-admin-generator` is licensed under [The MIT License (MIT)](license.md).
