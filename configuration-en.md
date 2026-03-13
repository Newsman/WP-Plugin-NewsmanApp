# Newsman Plugin for WordPress - Configuration Guide

This guide walks you through every setting in the Newsman plugin so you can connect your WordPress or WooCommerce store to your Newsman account and start collecting subscribers, sending newsletters, and tracking customer behavior.

---

## Where to Find the Plugin Settings

After installing and activating the plugin, look for the **NewsMAN** menu item in the left sidebar of your WordPress admin panel. Clicking it reveals several sub-pages:

- **NewsMAN** - The main page
- **Sync** - Choose which Newsman list receives your subscribers
- **Remarketing** - Track visitor behavior on your store for targeted campaigns
- **SMS** - Send text messages to customers when their order status changes (only available if you use WooCommerce)
- **Settings** - API connection, checkout options, and advanced settings
- **OAuth** - Quick setup by connecting directly to your Newsman account

---

## Getting Started - Connecting to Newsman

Before you can use any feature, you need to connect the plugin to your Newsman account. There are two ways to do this:

### Option A: Quick Setup with OAuth (Recommended)

1. Go to **NewsMAN > OAuth** in your WordPress admin.
2. Click **Connect with Newsman**.
3. You will be taken to the Newsman website. Log in if needed and grant access.
4. You will be redirected back to a Newsman admin page in WordPress where you choose your email list from a dropdown. Select the list you want to use and click **Save**.
5. That's it - your API Key, User ID, and List are all configured.

### Option B: Manual Setup

1. Log in to your Newsman account at newsman.app.
2. Go to your account settings and copy your **API Key** and **User ID**.
3. In WordPress, go to **NewsMAN > Settings**.
4. Paste your **API Key** and **User ID** in the corresponding fields.
5. Click **Save**. A green indicator will confirm the connection is successful.
6. Now go to **NewsMAN > Sync**. Because you entered a valid API Key and User ID, the **Select a list** dropdown will now show all the lists from your Newsman account. Pick the list you want to use and click **Save** again.

---

## Reconfigure with Newsman OAuth

If you need to reconnect the plugin to a different Newsman account, or if your credentials have changed, go to **NewsMAN > Settings** and click the **Reconfigure with Newsman OAuth** button. This will take you through the same OAuth flow described above - you will be redirected to the Newsman website to authorize access, then back to WordPress to select your email list. Your API Key, User ID, and List will be updated with the new credentials.

---

## Settings Page

Go to **NewsMAN > Settings** to configure the plugin behavior.

### General Settings

- **Allow API access** - Turn this on if you want Newsman to be able to pull data (such as products or subscribers) directly from your store. This is required for features like product feeds in your newsletters. Leave it off if you don't need this.

- **Send User IP Address** - When a visitor subscribes, the plugin can send their IP address to Newsman. This can help with analytics and compliance. If you turn this off, the plugin will use the **Server IP Address** you enter below instead.

- **Server IP Address** - A fallback IP address used when "Send User IP Address" is turned off. You can usually leave this empty.

- **Import Authorize Header Name / Key** - This is a legacy option for protecting your product feed with custom security credentials. If you connected via OAuth, you do not need to set these - the plugin handles authentication automatically. You only need to fill these in if you set up the connection manually and want to add an extra layer of security to product feed imports.

### Subscribe to Newsletter

- **Newsletter Opt-in type** - Choose how new subscribers are added:
  - **Opt-in** - The subscriber is added immediately.
  - **Double Opt-in** - The subscriber receives a confirmation email first and must click a link to confirm. This is recommended for GDPR compliance.

- **Confirmation email Form ID** - If you chose Double Opt-in above, enter the Form ID from your Newsman account. This tells Newsman which confirmation email template to send. You can find this ID in your Newsman dashboard under Forms.

### Checkout Options (WooCommerce only)

These settings add a newsletter checkbox to your store's checkout page, so customers can subscribe while placing an order.

- **Enable Newsletter Checkbox** - Turn this on to show a "Subscribe to our newsletter" checkbox on the checkout page.

- **Newsletter Checkbox Label** - Customize the text shown next to the checkbox. For example: "Yes, I want to receive special offers and news by email."

- **Checkbox checked by default** - If turned on, the checkbox will be pre-checked. Customers will need to uncheck it if they don't want to subscribe. Note: in some regions, pre-checking may not comply with privacy regulations.

- **Enable SMS sync** - When a customer places an order, their phone number will be synced to your Newsman SMS list. This allows you to send them text messages later.

- **Enable Order Status Checkbox** - Shows an additional checkbox asking customers if they want to receive SMS updates about their order status (e.g., "Your order has shipped").

- **Order Status Checkbox Label** - Customize the text for the order status checkbox.

- **Order Status checkbox checked by default** - Whether the order status checkbox is pre-checked.

### My Account Newsletter (WooCommerce only)

These settings add a dedicated newsletter page inside the customer's "My Account" area, where they can manage their subscription.

- **Enable** - Turn this on to add the newsletter page to My Account.

- **Page Menu Label** - The text shown in the My Account sidebar menu (e.g., "Newsletter").

- **Page Title** - The heading shown on the newsletter page (e.g., "Newsletter Subscription").

- **Checkbox Label** - The text next to the subscribe/unsubscribe checkbox (e.g., "I want to receive newsletters and promotions").

### Developer Settings

These settings are intended for advanced users and developers. In most cases, you should leave them at their default values.

- **Logging level** - Controls how much detail the plugin writes to its log file. The default is **Error**, which only logs problems. Set to **Debug** if you are troubleshooting an issue (but remember to set it back afterwards, as Debug mode creates large log files). Set to **No Logging** to disable logging entirely.

- **API Timeout** - How many seconds the plugin waits for a response from Newsman before giving up. The default of 10 seconds works well for most setups. Increase this only if you experience timeout errors on a slow server.

- **Enable Test User IP / Test User IP address** - For development and testing only. Lets you simulate a specific visitor IP address. Leave these off in production.

- **Plugin Loaded Priority** - Controls when the plugin initializes relative to other plugins. Only change this if you experience conflicts with another plugin. The default value of 20 works for most setups.

- **Use Action Scheduler** - If you have the Action Scheduler plugin installed, turning this on will process subscriptions and unsubscriptions in the background instead of immediately. This can improve checkout speed on high-traffic stores.

- **Use Action Scheduler for Subscribe / Unsubscribe** - Fine-grained control over which operations use background processing.

---

## Sync Page

Go to **NewsMAN > Sync** to choose where your subscribers are sent in Newsman.

- **Select a list** - Pick the Newsman email list that will receive your subscribers. All lists from your Newsman account are shown here.

- **Select a segment** - Optionally pick a segment within the selected list. Segments let you organize subscribers into groups (e.g., "VIP Customers", "Blog Readers"). If you don't use segments, leave this empty.

- **Select an SMS list** - Pick the Newsman SMS list for phone number synchronization.

---

## Remarketing Page

Go to **NewsMAN > Remarketing** to set up visitor tracking. Remarketing lets Newsman track what pages and products your visitors view, so you can send them personalized emails (e.g., abandoned cart reminders, product recommendations).

- **Use Remarketing** - Turn this on to enable the remarketing tracking pixel on your store.

- **Remarketing ID** - This is filled in automatically. It identifies your store in the Newsman tracking system. You don't need to change it.

- **Anonymize IP** - When turned on, visitor IP addresses are anonymized before being sent to Newsman. Recommended for GDPR compliance.

- **Send Telephone** - Include customer phone numbers in remarketing data. Only applies to logged-in customers who have provided a phone number.

- **Product Attributes** - Select which product attributes (e.g., Color, Size, Brand) are sent along with product view events. This lets you build more targeted campaigns in Newsman.

- **Customer Attributes** - Select which customer details are sent with remarketing data. Available options include billing/shipping company, city, state, and country.

- **Export WordPress Subscribers** - **OPTIONAL.** When checked, this allows Newsman to fetch WordPress users with the "Subscriber" role from your store.

- **Export WooCommerce Subscribers** - **OPTIONAL.** When checked, this allows Newsman to fetch email addresses from WooCommerce orders with a "Completed" status from your store.

**Important notes about Export WordPress Subscribers and Export WooCommerce Subscribers:**

- If you want to use this feature, **only check one of them, not both.** Having both options checked at the same time does not work.
- These options only allow Newsman to access the data. The actual import is configured in your Newsman account: go to **Newsman.app > Integrations > Plugins > WordPress / WooCommerce plugin > Subscribers**.
- In that Newsman configuration, you should set a **starting date** from which you want subscribers to be fetched. If you don't set a starting date, Newsman may import all subscribers from the beginning, which may not be what you want.

- **Export Orders on Status Change** - A multi-select dropdown where you choose which order statuses trigger sending order data to Newsman. For example, if you select "Completed" and "Processing", order details will be sent to Newsman whenever an order reaches one of those statuses. This enables revenue tracking and purchase-based campaigns.

---

## SMS Page (WooCommerce only)

Go to **NewsMAN > SMS** to configure automatic text messages sent to customers when their order status changes.

### Enabling SMS

- **Use SMS** - Turn this on to enable SMS notifications.

- **Select SMS List** - Choose which Newsman SMS list to use for sending messages.

### Order Status Messages

For each order status, you can enable a text message and customize its content:

| Order Status | When it's sent |
|-------------|----------------|
| **Pending** | Order received but not yet paid |
| **Failed** | Payment failed |
| **On Hold** | Awaiting payment confirmation (e.g., bank transfer) |
| **Processing** | Payment received, order is being prepared |
| **Completed** | Order has been fulfilled and shipped |
| **Refunded** | Order has been refunded |
| **Cancelled** | Order has been cancelled |

For each status, check the **Enable** box and write your message in the text area.

### Personalizing SMS Messages

You can use placeholders in your messages that will be replaced with actual order data. Wrap each placeholder in double curly braces:

| Placeholder | What it becomes |
|-------------|----------------|
| `{{billing_first_name}}` | Customer's first name (billing) |
| `{{billing_last_name}}` | Customer's last name (billing) |
| `{{shipping_first_name}}` | Customer's first name (shipping) |
| `{{shipping_last_name}}` | Customer's last name (shipping) |
| `{{order_number}}` | The order number |
| `{{order_date}}` | The date the order was placed |
| `{{order_total}}` | The total amount of the order |
| `{{email}}` | Customer's email address |

**Example message:**

> Hi {{billing_first_name}}, your order #{{order_number}} worth {{order_total}} has been shipped! Thank you for shopping with us.

### Courier Tracking Numbers (AWB)

If you use one of the supported Romanian courier plugins (Cargus, SameDay, or FanCourier), you can include the tracking number (AWB) in your SMS messages. These placeholders use conditional blocks - the content inside only appears if a tracking number exists:

**Example with Cargus:**

> Hi {{billing_first_name}}, your order #{{order_number}} has been shipped.{{if_cargus_awb}} Your tracking number is {{cargus_awb}}.{{endif_cargus_awb}}

The same pattern works for SameDay (`{{if_sameday_awb}}...{{sameday_awb}}...{{endif_sameday_awb}}`) and FanCourier (`{{if_fancourier_awb}}...{{fancourier_awb}}...{{endif_fancourier_awb}}`).

### Testing SMS

At the bottom of the SMS page, you'll find a test form. Enter a phone number and a message, then click **Send Test SMS** to verify that everything works before going live.

---

## Frequently Asked Questions

### Do I need WooCommerce to use this plugin?

No. The plugin works with a standard WordPress site for basic newsletter subscription and remarketing. However, the checkout checkbox, SMS notifications, order tracking, and customer export features require WooCommerce.

### What is the difference between Opt-in and Double Opt-in?

- **Opt-in**: The subscriber is added to your list immediately when they submit their email.
- **Double Opt-in**: The subscriber receives an email with a confirmation link. They are only added to your list after clicking that link. This ensures the email address is valid and that the person actually wants to subscribe. Double Opt-in is recommended for GDPR compliance.

### How do I find my Form ID for Double Opt-in?

1. Log in to your [Newsman account](https://newsman.app).
2. Select your **List** and go to **Settings > Subscription forms**.
3. **Create** or **Edit** a form.
4. Select **Landing page** and **Activate for newsletter subscription**.
5. Select **Embedded form** - the Form ID will be displayed there. Copy it and paste it into the **Confirmation email Form ID** field in the plugin settings.

### I connected via OAuth but my lists are empty. What should I do?

The list dropdown is populated by an API request to Newsman using your API Key and User ID. If the dropdown is empty, it means the connection to Newsman is not working. Go to **NewsMAN > Settings** and check that your API Key and User ID are correct and that the status indicator shows a valid connection. Every Newsman account has at least one list by default, so if the credentials are correct the lists will appear.

### Can I send SMS to customers from previous orders?

The SMS feature only triggers for new order status changes going forward. It does not retroactively send messages for past orders. However, you can use the **Export** feature on the Sync page to push existing customer phone numbers to your Newsman SMS list, and then send bulk SMS campaigns from the Newsman dashboard.

### What is the Action Scheduler and do I need it?

The Action Scheduler is a system that processes tasks in the background instead of immediately. It is included by default in latest WooCommerce installations, so if you run WooCommerce you most likely already have it. If your store has high traffic and you notice that the checkout is slow after enabling Newsman, you can enable it in Developer Settings to process subscriptions and unsubscriptions in the background. For most stores, this is not necessary.

### Where are the plugin logs?

The plugin uses the WooCommerce logging system, so logs are only available if WooCommerce is installed. You can find them in **WooCommerce > Status > Logs**. The logging level is controlled in the plugin's Developer Settings. If you're experiencing issues, set the logging level to **Debug**, reproduce the problem, and then check the WooCommerce logs for error messages. On a plain WordPress installation without WooCommerce, the plugin does not log.
