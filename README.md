# Launching laravel application

> [!NOTE]
> I've used Laravel Sail approach for building an application Dockerized.
> To run laravel sail you need to utilise MAC, LINUX or Windows with WSL2 and a docker engine running.
> In case you are using Windows WSL2, make sure to mount this project repository in WSL2 and run from there.
> Further info is available here: https://laravel.com/docs/12.x/sail

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

6) Run the migrations and seeders.
```
./vendor/bin/sail artisan migrate:refresh --seed
```

7) Build front-end
```
npm run build
```
8) Make sure that the project is running at [localhost](http://localhost)
9) Access phpMyAdmin at port 8080 [phpMyAdmin](http://localhost:8080) to view databases
10) Access mailpit at port 8025 [Mailpit](http://localhost:8025) to view emails, that are sent from the notifications/confirmations
## Run time commands
1) Start queue worker (for notifications)
```
./vendor/bin/sail artisan queue:work --sleep=3 --tries=3
```
2) Mass import for CSV files
```
./vendor/bin/sail artisan csv:import
```
3) Get payments by date
```
./vendor/bin/sail artisan report --date=YYYY-MM-DD
./vendor/bin/sail artisan report --date=2023-01-10
```
4) To test API endpoint for storing single payment, utilize POSTMAN, using data provided in task description
```
    {
        "firstname": "Lorem",
        "lastname": "Ipsum",
        "paymentDate": "2022-12-12T15:19:21+00:00",
        "amount": "99.99",
        "description": "Lorem ipsum dolorLN20221212 sit amet...",
        "refId": "dda8b637-b2e8-4f79-a4af-d1d68e266bf5"
    }
```
<img width="2004" height="411" alt="image" src="https://github.com/user-attachments/assets/a1840fbe-4d08-4e69-902e-e7b27d5099ef" />

## Project structure
### API Payments
1) Request goes through the ```api.php``` route ```api/payment/store```
2) Then it's proccessed through ```PaymentController```, where it's first validated by ```StorePaymentRequest```, and then goes to the ```store() method```
3) Then the Controller calls ```PaymentService``` which proccesses the payment, stores the data, queues ```SendPaymentConfirmation```, ```SendLoanPaidConfirmation``` or ```SendFailedPaymentNotification``` if it satisfies requirements for notifications
4) The ```PaymentService``` returns the processed data, the ```PaymentController``` sends out the HTTP json response.
### CSV Imports
1) The ```artisan csv:import``` command is ran, the ```console.php``` calls the ```PaymentImportService``` through the ```Artisan::command```
2) The ```PaymentImportService``` reads the CSV file, extracts file contents, proccesses the data by chunks, stores all the data also by chunks.
    - Queues ```SendPaymentConfirmation```, ```SendLoanPaidConfirmation``` or ```SendFailedPaymentNotification``` if it satisfies requirements for notifications.
    - ```PaymentImportService import()``` method yields data back to the console, which consumes the genetor for further data output in tables.
3) The whole CSV import is handled by chunks, the data is stored by chunk. The data to store, gets stored in one ```DB::transaction()``` for data safety and coherency.
    - If for example mass-inserts of payments are failed, the mass-updates to loans affected by said payments will also get cancelled and not updated since the payments are not registered. 
### Data location
1) The csv file is stored in ```storage\external\payments.csv```
2) The logs could be read in ```storage\logs\laravel.log```
3) The data about loans and customers, which were provided by the task could be found in the ```LoanSeeder``` and ```CustomerSeeder``` 
## Task notes
1) The invalid payments are not stored in the database, since
   - Code 1, Duplicates. Duplicates violate ```unique()``` constraints of ```Payment Reference```
   - Code 3, Invalid date. If data is Invalid it couldn't be stored in the database
   - Code 4, Loan Reference. If there's no ```Loan``` found with specified ```Loan Reference```, storing the ```Payment``` would violate ```ForeignKey``` constraint for ```Loan References``` in ```Payment table```
2) Extra tasks
   - Containerability: ```Laravel sail``` solution
   - Scalability: Scalability for mass imports by utilizing chunking, generator functions and database transactions. For the ability of working with larger and larger files, and not violating memory or other resource constraints, and keeping the stored data coherent.
   - Documentation: Documentation in this ```readme.md```
   - Testing: Not yet, I could add this in a day or two.
3) Things I have not done.
   - Project Front-End as it was not specified in the task
4) Things that could use improvement, but would take more time
   - Additional date format handling, more secure CSV file reading
   - Params for ```csv:import```, I'm not sure if it was worth adding, if you want to change the file or chunk size, just change CONSTS in ```PaymentImportService```
   - Logging, I wasn't sure which events should be logged or not. In case of failed payments in the batch it gets logged only in case of duplicate, and report about failed payments only comes from CSV payments, and singular failed API payment does not result in any logging or email report, just the data about which data is incorrect, since that's how API should be in case of Front-End. 
## Nice  to have

Sail Alias in ```~/.zshrc``` or ```~/.bashrc```
```
alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'
```
