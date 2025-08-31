# Launching laravel application

> [!NOTE]
> I've used Laravel Sail approach for building an application Dockerized.
> To run laravel sail you need to utilise MAC, LINUX or Windows with WSL2 and a docker engine running.
> In case you are using Windows WSL2, make sure to mount this project repository in WSL2 and run from there.
> Further info is available here: https://laravel.com/docs/11.x/sail

1) Clone the repo

```
git clone https://github.com/Kaleff/sun-finance-task.git
cd sun-finance-task
```
2) Copy, configure the .env.example file and rename the copy to .env
```
cp .env.example .env
```

3) Run the composer installation in the project directory

```
composer install
```

4) Generate APP_KEY for .env file

```
php artisan key:generate
```


5) Run the application using SAIL, make sure the docker engine is running

```
./vendor/bin/sail up
```

6) Run the migrations and seeders, 
```
./vendor/bin/sail artisan migrate:fresh -seed
```

7) Build front-end
```
npm run build
```
8) Make sure that the project is running at [localhost](http://localhost)
9) Access phpMyAdmin at port 8080 [mailpit](http://localhost:8080)

## Nice to have

Sail Alias in ```~/.zshrc``` or ```~/.bashrc```
```
alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'
```
