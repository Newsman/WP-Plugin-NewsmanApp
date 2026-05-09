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

- **Use Elementor Integration** - Master switch for the Newsman integration with Elementor Pro. **On by default.** When on, the Newsman section appears in the Elementor editor for both the legacy Form widget and Atomic Forms (Elementor 4.x), and submissions are pushed to your Newsman list. Turn off to remove all Newsman-added editor controls and stop submission forwarding for both. Existing Newsman per-form / per-field settings are preserved when off and reactivated when you turn it back on. Disabling has no effect on WordPress, WooCommerce, or non-Elementor newsletter flows.

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

- **Theme Cart Compatibility** - Enable for the most reliable detection of cart changes on any theme (background polling plus AJAX/fetch interception against the Newsman cart endpoint). Disable for a lighter mechanism that reads cart data directly from the WooCommerce Store API (`/wc/store/v1/cart/add-item` and `/wc/store/v1/batch`) responses (no polling). If you disable this option, use the newsman.app Remarketing **Check installation** tool to verify cart events are detected on your theme.

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

AWB SMS messages are **not sent automatically**. This is by design, because courier plugin implementations vary greatly between stores and have many customizations. Instead, for each supported courier, when an order has an AWB number attached to it, a **Send SMS** option appears in the **Order Actions** dropdown (top right of the admin order page). The admin can manually trigger the SMS from there. If needed, your store's developers can extend this feature to send AWB SMS automatically based on your store's specific business logic.

### Testing SMS

At the bottom of the SMS page, you'll find a test form. Enter a phone number and a message, then click **Send Test SMS** to verify that everything works before going live.

---

## Elementor Forms (Elementor Pro)

If your site uses Elementor Pro's Form widget, the plugin can subscribe submissions to a Newsman list and send any other field values as subscriber properties. This applies to every place the Form widget is used, including forms placed inside Elementor popups.

> The Form widget is part of Elementor Pro, not the free Elementor plugin. If only the free Elementor is installed, the Newsman section described below will not appear.

> **Tested with:** Elementor and Elementor Pro **3.35.x** and **4.x**. The legacy Form widget integration applies to both versions; the Atomic Forms integration (described further below) only applies to **4.x**, since Atomic Forms does not exist in 3.35.x.

### Per-form Settings

Open any page in the Elementor editor and select a Form widget. In the **Content** tab, after the **Form Fields** section, you will see a new **Newsman** section with two settings:

- **Send to Newsman** - Off by default. Turn this on to enable Newsman for this form. When off, no data from the form is sent to Newsman regardless of the per-field options below.

- **Newsman List** - Visible only when **Send to Newsman** is on. Pick the Newsman list that should receive submissions from this form. The dropdown is populated automatically from your Newsman account using the API Key and User ID configured in **NewsMAN > Settings**, and is cached for 10 minutes. If the dropdown is empty, see the troubleshooting note at the end of this section.

### Per-field Settings

Each individual field in the form has two extra options under its **Advanced** tab:

- **Send to Newsman** - On by default for new fields. When on, this field's value is included in the submission to Newsman as a subscriber property. The field's **ID** (set in **Advanced > Custom ID**) becomes the property key in Newsman, so use stable, snake_case IDs like `phone`, `company`, or `birthdate`.

- **Use as Newsman email** - Off by default. Mark exactly one field with this option to indicate which field holds the subscriber's email address. Only **Email** and **Text** field types can be marked. If no field is marked, or the marked field is empty at submission time, the form will fail with an inline error.

> Tip: the **Custom ID** of an Elementor field is what you set in the field's **Advanced > Custom ID** input. The default IDs are auto-generated (for example `field_a1b2c3d`) and not very readable in Newsman. We recommend you set a meaningful Custom ID on every field you mark as **Send to Newsman**.

### What happens on submission

When a visitor submits the form:

1. If **Send to Newsman** is off, nothing happens.
2. If on, the plugin reads the email field and the per-field property values, then subscribes (or refreshes) the email to the selected list. The subscriber's properties in Newsman are updated with the field values.
3. If the Newsman API rejects the request (missing email, invalid list, expired credentials), the form does not show its success state - the visitor sees an inline error message and Elementor's built-in actions (such as redirect or confirmation email) do not run. The technical error is also written to the WooCommerce log if logging is enabled.

### Limitations

- This integration targets the **legacy Form widget**. Elementor 4.x's new **Atomic Forms** widget uses a different architecture and is covered separately in the **Atomic Forms** section below.
- Forms inside Elementor popups work the same way - the integration is on the Form widget itself, not the page or popup that contains it.
- Multi-step forms work; the Newsman section applies to the form as a whole, not per step.

### Troubleshooting: empty Newsman List dropdown

If the **Newsman List** dropdown is empty in the Elementor editor:

1. Confirm that **NewsMAN > Settings** shows a valid API connection (green indicator next to the credentials).
2. The list dropdown is cached for 10 minutes per site. If you just changed your credentials, wait up to 10 minutes or clear your site's transients for the cache to refresh.
3. Newsman accounts always have at least one list, so an empty dropdown almost always indicates a credentials issue rather than an empty account.

---

## Atomic Forms (Elementor 4.x, experimental)

Elementor 4.x introduced **Atomic Forms**, a separate form architecture where the form, its labels, inputs, textareas, checkboxes, and submit button are each independent atomic widgets you arrange directly on the canvas. The Newsman plugin supports Atomic Forms with the same on/off toggle, list dropdown, and per-field settings as the legacy Form widget, with one significant limitation noted below.

> Atomic Forms is gated behind two Elementor experiments (`Atomic Widgets` in core and `Atomic Form` in Pro). Both default to active in Elementor 4.x, but you can disable them under **Elementor > Settings > Features**. The Newsman section will not appear if either experiment is off.

> Atomic Forms is currently in development status (`RELEASE_STATUS_DEV`). Elementor may change the underlying API in any minor release. If a future Elementor update breaks the Newsman section in Atomic Forms, the legacy Form widget integration is unaffected and will keep working.

### Per-form settings

Open a page with an Atomic Form on the canvas. Click the form container (the parent element holding the inputs) to select it. Below the existing **Content** and **Settings** sections in the editor panel, a new **Newsman** section appears with two settings:

- **Send to Newsman** - Off by default. Turn this on to enable Newsman for this form. When off, no data is sent to Newsman regardless of per-input settings.

- **Newsman List** - Pick the Newsman list that should receive submissions. The dropdown is shared with the legacy Forms integration: lists come from your Newsman account using the API Key and User ID configured in **NewsMAN > Settings**, cached for 10 minutes.

### Per-input settings

Click any **Input**, **Textarea**, or **Checkbox** widget inside the form. The editor panel for that widget gets a **Newsman** section with:

- **Send to Newsman** - On by default. When on, this widget's submitted value is included as a subscriber property. The property key is the widget's **ID** (set in the **Settings** section, control labelled **ID**); we recommend setting a stable, snake_case ID like `phone`, `company`, or `birthdate`.

- **Use as Newsman email** - Off by default. Mark exactly one input or textarea with this option to indicate which one holds the subscriber's email address. The Checkbox widget does not expose this option (a checkbox cannot be the email field).

> The widget **ID** in Atomic Forms is the same field used for the HTML `name` attribute when the form is submitted. The two must match for the integration to find the value, which is why we use the ID directly.

### What happens on submission

When a visitor submits the form:

1. If **Send to Newsman** is off on the form, nothing happens.
2. If on, the plugin reads the form's saved settings, identifies the email input by the **Use as Newsman email** marker, builds a properties map from the inputs marked **Send to Newsman**, and subscribes the email to the chosen Newsman list with those properties.

### Limitation: errors are not surfaced inline (v1)

Unlike the legacy Form widget integration, **Atomic Forms does not let third-party integrations write error messages to the form's response**. The submission ajax flow runs Elementor's own action runner (Email, Webhook, Collect submissions), and Elementor's hardcoded action whitelist blocks Newsman from registering as a real form action. We therefore listen alongside the official flow rather than inside it.

The practical consequence: if Newsman fails (invalid email, no list configured, expired credentials, network error), the form will **still show its success state** to the visitor. The failure is logged to the WooCommerce logs (when WooCommerce is installed) under the Newsman source. Plain WordPress installs have no log destination - check **NewsMAN > Settings** credentials if Newsman submissions seem to be silently missing.

We expect Elementor to expand the Atomic Forms extension API in a future release; we will surface inline errors at that point.

### Troubleshooting

- **Newsman section does not appear**: confirm both atomic experiments are active in **Elementor > Settings > Features**. Both `Atomic Widgets` and `Atomic Form` must be on.
- **Newsman List dropdown is empty**: same as the legacy Forms integration - see the troubleshooting note in the **Elementor Forms** section above.
- **Submitter email not appearing in Newsman**: make sure exactly one input/textarea has **Use as Newsman email** turned on, and that the same widget has a meaningful **ID**.

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
