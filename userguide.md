# Board Meeting Management System – User Manual

## Overview

This application helps you organize and manage board meetings, including agendas, attendees, resolutions, and meeting minutes. All data is stored securely in a database, and you can export agendas and minutes as PDF documents.

---

## Getting Started

### Logging In
1. Open the application in your web browser
2. Enter your credentials (username and password)
3. Click **Login**
4. If you have Two-Factor Authentication (2FA) enabled, you'll be prompted to enter a 6-digit code from your authenticator app (see [Two-Factor Authentication](#two-factor-authentication-2fa) section)

### Forgot Password
If you've forgotten your password:
1. Click **Forgot Password?** on the login page
2. Enter your email address
3. Check your email for a password reset link
4. Click the link to create a new password
5. Log in with your new password

**Note:** Password reset links expire after a set time period for security.

### Main Navigation
- **Dashboard** – Overview of system statistics and upcoming meetings
- **Board Meetings** – View and manage all meetings
- **Members** – Manage board members (Admin only)
- **Resolutions** – View and manage resolutions
- **Users** – Manage user accounts (Admin only)
- Other sections appear based on your user role and permissions

---

## Dashboard

The Dashboard provides a quick overview of your system's key statistics and upcoming activities.

### Dashboard Features
- **Statistics Cards** showing:
  - Active Members count
  - Upcoming Meetings (next 30 days)
  - Recent Meetings (last 30 days)
  - Pending Resolutions
  - Draft Minutes
- **Meeting Type Filter** – Filter statistics by a specific meeting type, or view all meeting types
- **Upcoming Meetings List** – Quick access to meetings scheduled in the near future

### Using the Dashboard
1. The dashboard loads automatically when you log in
2. Use the **Meeting Type** dropdown to filter statistics by meeting type (optional)
3. Click on any meeting title to view its details
4. Statistics update automatically based on your selected filter

---

## Managing Meeting Types

Meeting types help you organize different kinds of meetings (e.g., Board Meetings, Standing Committee, Presbytery in Council, etc.). Each meeting type can have its own agenda templates and member assignments.

### Creating a Meeting Type (Admin Only)
1. From the Dashboard, click **+ New Meeting Type**
2. Enter:
   - **Name** – The meeting type name (e.g., "Standing Committee")
   - **Description** – Optional description
   - **Shortcode** – Optional 3-letter code
3. Click **Save**

### Managing Meeting Types
- Meeting types are managed by Administrators
- Each meeting type can have its own set of board members with specific roles
- Agenda templates can be created per meeting type

---

## Managing Board Members (Admin Only)

Board members can belong to multiple meeting types, each with different roles and statuses.

### Adding a Board Member
1. Navigate to **Members** from the main menu
2. Click **+ Add Member**
3. Fill in the member information:
   - **First Name** and **Last Name** – Required
   - **Email** – Contact email (optional)
   - **Phone** – Contact phone number (optional)
   - **Title** – Job title or position (optional)
   - **Bio** – Brief biography (optional)
4. Click **Save**

### Assigning Members to Meeting Types
1. After creating a member, or when editing an existing member:
2. In the member details, you can assign them to meeting types
3. For each meeting type, specify:
   - **Role** – Chair, Deputy Chair, Secretary, Treasurer, Member, or Ex-officio
   - **Status** – Active or Inactive
   - **Start Date** and **End Date** – Optional dates for the membership
4. Click **Save**

**Note:** A board member can have different roles in different meeting types.

### Editing or Deleting Board Members
1. Click on a member in the members list to view their details
2. Click the pencil icon to edit
3. Update the information and click **Save**
4. Click the trash icon to delete a member (Admin only)
5. Confirm the deletion

### Filtering Members by Meeting Type
- Use the **Meeting Type** dropdown at the top of the Members page to filter members by their assigned meeting types
- Select "All Members" to view everyone

---

## Managing Meetings

### Creating a New Meeting
1. Click **+ New Meeting** button at the top of the meetings page
2. Select a **Meeting Type** from the dropdown (e.g., Standing Committee, PIC, RPC, PRC, etc)
3. Fill in the meeting details:
   - **Title** – Name or title of the meeting
   - **Meeting Date** – Date and time of the meeting
   - **Location** – Where the meeting will be held (optional)
   - **Virtual Meeting Link** – URL for online meetings (optional)
   - **Status** – Scheduled, In Progress, Completed, or Cancelled
   - **Notes** – Additional information about the meeting (optional)
4. *(Optional)* Check **Apply Template** to auto-populate agenda items from a saved template
5. Click **Save Meeting**

### Viewing & Editing a Meeting
1. Click on a meeting in the list to open its detail view
2. You'll see tabs for:
   - **Agenda** – List of agenda items
   - **Attendees** – Who attended or will attend
   - **Minutes** – Meeting notes and decisions
   - **Resolutions** – Formal decisions made
3. To edit the meeting, click **Edit Meeting** (pencil icon)
4. Update the details and click **Save**
5. To generate a meeting notice, click **📋 Generate Notice** button (available in the meeting detail view)

### Deleting a Meeting
1. Open the meeting detail view
2. Click **Delete Meeting**
3. Confirm the deletion

---

## Managing Agendas

### Adding Agenda Items
1. Open a meeting and go to the **Agenda** tab
2. Click **+ Add Agenda Item**
3. Fill in the form:
   - **Item Title** – Brief description (e.g., "Financial Report")
   - **Description** – More details about what will be discussed
   - **Duration** – Estimated time for this item (optional)
   - **Assigned To** – Board member leading this item (if applicable)
4. Click **Save Agenda Item**

**Note:** Item numbers are generated automatically in `YY.MM.SEQ` format (e.g., `25.01.001` for the first item in January 2025).

### Uploading Documents to Agenda Items
1. In the **Agenda** tab, click **Upload Document** (paperclip icon) next to an agenda item
2. Select a PDF file from your computer
3. Click **Upload**

**Supported formats:** PDF only. Maximum file size is set by your administrator.

### Reordering Agenda Items
- Drag agenda items up or down to change the order, or
- Click **↑** and **↓** buttons next to each item

### Editing or Deleting Agenda Items
1. Click the pencil icon to edit an agenda item
2. Make your changes and click **Save**
3. Click the trash icon to delete an item (confirm the deletion)

---

## Managing Attendees

### Adding Attendees
1. Open a meeting and go to the **Attendees** tab
2. Click **+ Add Attendee**
3. Select a board member from the list
4. Choose their attendance status:
   - **Present** – Attended the meeting
   - **Absent** – Did not attend
   - **Apologies** – Sent regrets
5. Assign a role if needed (Chair, Vice-Chair, etc.)
6. Click **Save**

### Editing or Removing Attendees
1. Click the pencil icon to edit an attendee's details
2. Click the trash icon to remove them from the meeting
3. Confirm any deletions

---

## Recording Minutes

### Creating Minutes
1. Open a meeting and go to the **Minutes** tab
2. Click **+ Create Minutes**
3. The agenda items will be pre-loaded into the form
4. For each agenda item, you can:
   - Add **Comments** – Notes about what was discussed
   - Record **Decisions** – What was agreed upon
5. Fill in general meeting notes and outcomes
6. Click **Save Minutes**

### Editing Minutes
1. In the **Minutes** tab, click **Edit Minutes**
2. Update the content
3. Click **Save**

### Approving Minutes
1. After recording, click **Approve Minutes**
2. Once approved, minutes are locked from further editing (by design)

### Exporting Minutes as PDF
1. In the **Minutes** tab, click **Export as PDF**
2. The system will generate and download a PDF document

---

## Recording Resolutions

### Adding a Resolution
1. Open a meeting and go to the **Resolutions** tab
2. Click **+ Add Resolution**
3. Fill in:
   - **Resolution Title** – Short description of the decision
   - **Description** – Full text of the resolution
   - **Status** – Draft, Approved, or other status as defined by your organization
4. Click **Save Resolution**

### Editing or Deleting Resolutions
1. Click the pencil icon to edit
2. Click the trash icon to delete
3. Confirm any deletions

---

## Agenda Templates

### What is an Agenda Template?
A template lets you save a standard set of agenda items for a meeting type. When you create a new meeting and apply the template, these items are automatically added.

### Creating or Managing Templates
1. Click **Manage Agenda Template** button (appears when a meeting type is selected)
2. Click **+ Add Template Item**
3. Enter:
   - **Item Title**
   - **Description**
   - **Duration** (optional)
4. Click **Save**
5. Reorder items by dragging or using **↑** and **↓** buttons
6. Delete items with the trash icon

### Using a Template When Creating a Meeting
1. Click **+ New Meeting**
2. Check **Apply Template**
3. Complete the meeting details
4. Click **Save Meeting**

The template items will be automatically added to the agenda.

---

## Exporting Documents

### Exporting an Agenda
1. Open a meeting detail view
2. In the **Agenda** tab, you can:
   - Click **Export Agenda as HTML** – Opens a print-friendly HTML page
   - Click **Export Agenda as PDF** – Downloads a PDF document
3. The export includes:
   - All agenda items and their descriptions
   - Item numbers in YY.MM.SEQ format
   - Attached PDF documents (merged into a single PDF if available)
   - Organization logo (if configured)

### Exporting a Meeting Notice
1. Open a meeting detail view
2. Click **📋 Generate Notice** button
3. You'll see options to:
   - **View HTML Notice** – Opens a print-friendly HTML page with meeting details
   - **Download PDF Notice** – Downloads a PDF document
4. The notice includes:
   - Meeting title and type
   - Date, time, and location
   - Virtual meeting link (if provided)
   - Status and additional notes
   - Organization logo (if configured)

### Exporting Minutes as PDF
1. Go to the **Minutes** tab
2. Click **Export Minutes as PDF**
3. The PDF includes all minutes content, comments, and decisions

---

## User Management (Admin Only)

Administrators can manage user accounts, assign roles, and link users to board members.

### Adding a User
1. Navigate to **Users** from the main menu
2. Click **+ Add User**
3. Fill in the user information:
   - **Username** – Unique login name (required)
   - **Password** – Initial password (user can change it later)
   - **Email** – User's email address (required)
   - **Role** – Admin, Clerk, Member, or Viewer (see [User Roles](#user-roles) below)
   - **Link to Board Member** – Optionally link this user account to a board member profile
   - **Active Status** – Enable or disable the account
4. Click **Save**

### Editing or Deleting Users
1. Click on a user in the users list
2. Click the pencil icon to edit
3. You can update:
   - Email address
   - Role
   - Password (leave blank to keep current password)
   - Board member link
   - Active status
4. Click **Save**
5. Click the trash icon to delete a user (confirm the deletion)

### User Roles

| Role | Permissions |
|------|-------------|
| **Admin** | Full access: manage users, members, meeting types, delete anything |
| **Clerk** | Manage meetings, agendas, minutes, attendees, documents, resolutions |
| **Member** | View all data, edit own attendance, limited write access |
| **Viewer** | Read-only access to all data |

---

## Two-Factor Authentication (2FA)

Two-Factor Authentication adds an extra layer of security to your account by requiring a code from an authenticator app in addition to your password.

### Setting Up 2FA
1. Log in to your account
2. Click **2FA** in the header navigation
3. Click **Enable 2FA**
4. You'll see a QR code on the screen
5. Open your authenticator app on your phone (Google Authenticator, Authy, Microsoft Authenticator, 1Password, etc.)
6. Scan the QR code with your app
   - **Alternative:** If you can't scan, manually enter the secret key shown below the QR code
7. Enter the 6-digit code from your authenticator app to verify
8. Click **Verify and Enable**
9. **Important:** Save your backup codes in a secure location (password manager, encrypted file, etc.)
   - Backup codes can be used if you lose access to your authenticator device
   - Each backup code can only be used once

### Logging In with 2FA Enabled
1. Enter your username and password as usual
2. After successful password verification, you'll be redirected to the 2FA verification page
3. Enter the 6-digit code from your authenticator app
   - Codes refresh every 30 seconds
   - The code field will auto-submit when you enter 6 digits
4. Or use a backup code if you don't have access to your device
5. Click **Verify** to complete login

### Disabling 2FA
1. Go to the **2FA** page in your account
2. Click **Disable 2FA**
3. Confirm the action
4. Your account will return to password-only authentication

### Troubleshooting 2FA
- **Codes not working?** Make sure your device's clock is synchronized (TOTP is time-sensitive)
- **Lost your device?** Use one of your backup codes to log in, then disable 2FA and set it up again with your new device
- **Backup codes lost?** Contact your system administrator to disable 2FA for your account
- **QR code not displaying?** Use the manual secret key entry option instead

---

## Tips & Best Practices

- **Use Meeting Types:** Organize meetings by type (Board, Committee, etc.) to keep them organized and apply appropriate templates
- **Set Durations:** Help keep meetings on track by setting realistic time estimates for each agenda item
- **Document Everything:** Attach supporting documents to agenda items so all context is available in exports
- **Approve Minutes Promptly:** Once minutes are approved, they become a permanent record
- **Templates Save Time:** Create templates for recurring meetings to speed up future meeting setup
- **Enable 2FA:** Protect your account by enabling Two-Factor Authentication for enhanced security
- **Use Meeting Notices:** Generate and distribute meeting notices to keep all participants informed
- **Keep Member Information Updated:** Regularly update board member contact information and roles
- **Filter Views:** Use meeting type filters on Dashboard and Members pages to focus on specific groups
- **Export Regularly:** Export important agendas and minutes as PDFs for archival purposes

---

## Troubleshooting

### Can't Upload a Document?
- Make sure the file is a PDF
- Check that the file size is under the maximum allowed (ask your administrator if unsure)
- Ensure your browser allows file uploads

### Missing Attendees or Members?
- Contact your system administrator to add new board members to the system
- Attendees must be registered in the system before they can be added to a meeting

### PDF Export Issues?
- If exports fail, check that your browser's popup blocker isn't blocking the download
- Contact your administrator if the PDF cannot be generated

### Permission Errors?
- Some actions (deleting meetings, approving minutes, managing members) may be restricted based on your role
- Contact your administrator if you believe you should have access to an action

### 2FA Issues?
- If you can't log in after enabling 2FA, try using a backup code
- Ensure your device's clock is synchronized (TOTP codes are time-sensitive)
- Contact your administrator if you've lost both your device and backup codes

### Can't Generate Meeting Notices?
- Meeting notices are available from the meeting detail view
- Make sure the meeting has a title and scheduled date
- Contact your administrator if the notice generation fails

---

## Need Help?

For technical support or feature requests, contact your system administrator or the development team.