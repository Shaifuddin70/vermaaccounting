# Tax Intake Form: Full Field List

Taken from keentax.ca/intake on 2026-10-12 (read from the page's code, nothing submitted).
Field labels and options are exact. Short helper notes are my own summaries.

**Legend:** `*` = required · `→ IF` = follow-up field, shown only when the condition is met · `[+ Add another]` = repeatable block · `{year}` = the tax year selected on step 0

**Field types:** Yes/No = two-button toggle · Date = YYYY-MM-DD · $ = money input · Dropdown = single choice

---

## Step 0: Start

| Field | Type | Options |
|---|---|---|
| Select Tax Year * | Dropdown | 2025, 2024, 2023, 2022, 2021 |

Button: **Get Started**. Trust badges: "Your info is encrypted & secure" · "Auto-saves as you go" · "~15 min to complete"

**Returning client screen** ("Welcome back!"): Social Insurance Number *, Last Name *, Date of Birth * → **Continue**. After SIN + DOB are entered on step 1, they also offer **"Pre-fill my information"** from last year's file.

---

## Step 1: About You (always shown)

**Identification**
| Field | Type | Notes |
|---|---|---|
| Social Insurance Number * | Text, auto-formats 123-456-789 | 9 digits |
| Date of Birth * | Date | Must match government ID |

**Your Name**
| Field | Type | Notes |
|---|---|---|
| First Name * | Text | As on SIN/passport |
| Last Name * | Text | As on SIN/passport |
| Middle Name | Text | Optional |

**Contact**
| Field | Type | Notes |
|---|---|---|
| Email * | Email | |
| Phone Number * | Phone, auto-formats (123)456-7890 | Number CRA can reach you on |
| Is your WhatsApp number the same as your phone number? | Yes/No (default Yes) | |
| → IF No: WhatsApp Country Code * | Dropdown | +1 (Canada/US), +44 (UK), +91 (India), +61 (Australia), +971 (UAE), +92 (Pakistan), +880 (Bangladesh), +234 (Nigeria), +254 (Kenya), +27 (South Africa) |
| → IF No: WhatsApp Number * | Phone | |

**Marital Status**
| Field | Type | Options / Notes |
|---|---|---|
| What is your current marital status? * | Dropdown | Married, Living Common-Law, Widowed, Divorced, Separated, Single. Status on Dec 31 of {year} |
| Did your marital status change during the year {year}? | Yes/No | |
| → IF Yes: When did it change? | Date | |

**Preferences**
| Field | Type | Options / Notes |
|---|---|---|
| Preferred language for CRA correspondence | Dropdown | English (default), French |
| Are you a Canadian citizen? | Yes/No | |
| Did you permanently leave Canada during the year? | Yes/No | |
| → IF Yes: Date you left Canada | Date | |
| Do you have a CRA My Account? | Yes/No | |
| Authorize Elections Canada to use your information to update voter registration? | Yes/No | Optional |

---

## Step 2: Your Address (always shown)

**Address**
| Field | Type | Notes |
|---|---|---|
| Unit / Apt | Text | |
| Street * | Text | Number + street name |
| P.O. Box | Text | Optional |
| City * | Text | |
| Province / Territory * | Dropdown | All provinces/territories |
| Postal Code * | Text | |
| Country | Text | Default "Canada" |

**Province on December 31**
| Field | Type | Notes |
|---|---|---|
| Which province/territory did you live in on December 31, {year}? * | Dropdown | Auto-fills from Province above |
| Did you move to a different province during {year}? | Yes/No | |
| → IF Yes: Which province did you move from? | Dropdown | |
| → IF Yes: Date of Move * | Date | |

---

## Step 3: Your Tax Situation (always shown)

These Yes/No answers decide which later steps and document uploads appear.

**Important Questions** (all required)
| Field | Type | Follow-up / Unlocks |
|---|---|---|
| Have you filed taxes before? * | Yes/No | → IF No: **What year are you filing for the first time?** (number) |
| Did you recently move to Canada in {year}? * | Yes/No | IF Yes → **Newcomer Info** step |
| Were you a student in {year}? * | Yes/No | → IF Yes: **Estimated tuition paid in {year}** ($). Also asks for T2202 upload |
| Did you work for an employer in {year}? * | Yes/No | → IF Yes: **Estimated total income in {year}** ($). Also asks for T4 upload |
| Did you earn money from Uber/Lyft, your own business, or freelancing? * | Yes/No | IF Yes → **Self-Employment** step |
| Did you move to be closer to a new job or school? * | Yes/No | IF Yes → **Moving Expenses** step |
| Did you pay rent in {year}? * | Yes/No | IF Yes → **Rent Paid** step |

**About Your Situation**
| Field | Type | Unlocks |
|---|---|---|
| Do you have children or other family members who depend on you financially? | Yes/No | IF Yes → **Dependants** step |
| Would you like to register as an organ and tissue donor? | Yes/No | (Ontario returns) |
| Do you have a disability approved by the CRA? | Yes/No | |
| Did you file for bankruptcy during {year}? | Yes/No | |

**Income You Received**
| Field | Type | Unlocks |
|---|---|---|
| Did you collect rent from a property you own? | Yes/No | **Rental Income** step |
| Did you earn interest or dividends from a bank or investments? | Yes/No | T5 + T3 uploads |
| Did you sell any stocks, crypto, or property? | Yes/No | T5008 + capital gains doc uploads |
| Did you receive Employment Insurance (EI) benefits? | Yes/No | T4E upload |
| Did you receive a pension? | Yes/No | T4A / T4A(OAS) / T4A(P) upload |
| Did you receive social assistance? | Yes/No | T5007 upload |
| Did you receive Workers' Compensation? | Yes/No | T5007 (WSIB) upload |
| Did you receive a scholarship, bursary, or grant? | Yes/No | |
| Did you earn any income from outside Canada? | Yes/No | **Other Income** step |
| Did you earn tips that were not included on your pay stub? | Yes/No | **Other Income** step |

**Money You Spent**
| Field | Type | Unlocks |
|---|---|---|
| Did you put money into an RRSP, PRPP, or FHSA? | Yes/No | **RRSP / FHSA** step + receipt upload |
| Did you pay for child care? | Yes/No | **Child Care** step + receipt upload |
| Did you pay for medical or dental expenses out of your own pocket? | Yes/No | Medical receipts upload |
| Did you donate to a charity or organization? | Yes/No | Donation receipts upload |
| Did you pay alimony or child support to an ex-spouse? | Yes/No | |
| Did you pay interest on a government student loan? | Yes/No | |
| Did you work from home for your employer? | Yes/No | **Work From Home** step + T2200 upload |
| Did your employer require you to pay for work expenses? | Yes/No | T2200 upload |
| Did you pay union or professional dues? | Yes/No | |

**Housing**
| Field | Type | Unlocks |
|---|---|---|
| Did you pay property tax on a home you own? | Yes/No | Property tax bill upload |
| Did you buy your first home in {year}? | Yes/No | |
| Do you own property outside Canada worth over $100,000? | Yes/No | |

---

## Step 4: Spouse / Partner
**Shown only if** marital status = Married or Living Common-Law.

| Field | Type |
|---|---|
| Spouse's First Name * | Text |
| Spouse's Last Name * | Text |
| Spouse's SIN | SIN format |
| Spouse's Date of Birth | Date |
| Their net income in {year} | $ (Line 23600 or estimate) |
| Is your spouse self-employed? | Yes/No |
| Current country of residence * | Text |
| Is your spouse also filing their taxes with us? | Yes/No → IF Yes: shows a note about linking returns |

---

## Step 5: Dependants  `[+ Add another dependant]`
**Shown only if** "Do you have children or other family members who depend on you financially?" = Yes.

| Field | Type | Options |
|---|---|---|
| First Name * | Text | |
| Last Name * | Text | |
| Date of Birth | Date | |
| Relationship | Dropdown | Child, Grandchild, Parent, Grandparent, Sibling, Niece/Nephew, Aunt/Uncle, Other |
| SIN (if available) | SIN format | |
| Their net income | $ | |
| Lives with you? | Yes/No (default Yes) | |
| Has a disability? | Yes/No | |
| Is a student? | Yes/No | |
| Claim child care expenses for this dependant? | Yes/No | |

---

## Step 6: Self-Employment  `[+ Add another business]`
**Shown only if** "Did you earn money from Uber/Lyft, your own business, or freelancing?" = Yes.
Page title: "Self-employment / Business income (Uber / Lyft)"

| Field | Type | Options |
|---|---|---|
| Business Name | Text | |
| Business Type | Dropdown | Sole Proprietorship, Partnership |
| Industry | Dropdown | Rideshare (Uber/Lyft), Delivery (DoorDash/Skip/Instacart), Freelance/Consulting, Retail, Construction, Real Estate, Food Service/Restaurant, Health/Wellness, Technology/IT, Arts/Entertainment, Trucking/Transportation, Cleaning Services, Other |
| Total Gross Income * | $ | Before expenses |

**→ "Add business expenses" button** (expands; all $):
Advertising · Meals & Entertainment (50% deductible) · Insurance · Interest & Bank Charges · Office Expenses · Supplies · Legal & Accounting · Rent · Salaries & Wages · Travel · Phone & Utilities · Other Expenses

**Vehicle** (inside the expanded section)
| Field | Type | Options |
|---|---|---|
| Make / Model of Vehicle | Text | |
| Vehicle Class | Dropdown | Class 10 - Car, van, pickup (most vehicles) · Class 10.1 - Passenger vehicle above CRA limit · Class 54 - Zero-emission vehicle · Other / Not sure |
| Is this a motor vehicle? | Yes/No | |
| Purchase Date | Date | |
| Total KM driven in {year} | Number | |
| KM driven for business | Number | |
| Fuel and Oil | $ | |
| Maintenance and Repairs | $ | |
| Insurance | $ | |
| Registration and Licenses | $ | |
| Electricity (zero-emission vehicles) | $ | |
| Parking Expenses | $ | |
| Supplementary Business Insurance | $ | |
| Other Motor Vehicle Expense 1 | $ | |
| Other Motor Vehicle Expense 2 | $ | |

**GST/HST**
| Field | Type |
|---|---|
| Registered for GST/HST? | Yes/No |
| → IF Yes: GST/HST Number | Text (e.g. 123456789 RT0001) |
| → IF Yes: GST/HST Collected | $ |
| → IF Yes: GST/HST Paid on Expenses | $ |

---

## Step 7: Rental Income  `[+ Add another property]`
**Shown only if** "Did you collect rent from a property you own?" = Yes.

| Field | Type | Options |
|---|---|---|
| Property Address * | Text | |
| Landlord Name * | Text | |
| Property Type | Dropdown | House, Apartment/Condo, Room, Commercial, Other |
| # of Units | Number (default 1) | |
| Your Ownership % | Number (default 100) | |
| Total Gross Rent Collected * | $ | |

**→ "Add rental expenses" button** (all $): Advertising · Insurance · Mortgage Interest · Legal & Accounting · Repairs & Maintenance · Property Management · Property Taxes · Utilities · Condo Fees · Other Expenses

---

## Step 8: Other Income  `[+ Add another income]`
**Shown only if** foreign income = Yes OR unreported tips = Yes.

| Field | Type | Options |
|---|---|---|
| Type of Income * | Dropdown | Tips/Gratuities, Scholarships/Bursaries/Grants, Support Payments Received, Foreign Rental Income, Foreign Pension, Other Foreign Income, Casual/Occasional Earnings, Other |
| Description | Text | |
| Amount (CAD) * | $ | |
| Country (if foreign) | Text | |
| → IF type contains "Foreign": Foreign Tax Paid | $ | |

---

## Step 9: RRSP / FHSA  `[+ Add another contribution]`
**Shown only if** "Did you put money into an RRSP, PRPP, or FHSA?" = Yes.

| Field | Type | Options |
|---|---|---|
| Type | Dropdown | RRSP (default), Spousal RRSP, PRPP, FHSA (First Home Savings) |
| Financial Institution | Text | |
| Contribution Amount * | $ | |
| Receipt Number | Text | Optional |
| Was this contributed in the first 60 days of {year+1}? | Yes/No | |

---

## Step 10: Child Care  `[+ Add another provider]`
**Shown only if** "Did you pay for child care?" = Yes.

| Field | Type | Options |
|---|---|---|
| Child's Name * | Text | |
| Child's Date of Birth | Date | |
| Type of Care | Dropdown | Daycare Centre, Home Care Provider, Day Camp/Sports School, Nanny/Babysitter, Boarding School, Other |
| Care Provider Name * | Text | |
| Provider SIN or Business Number | Text | |
| Total Amount Paid * | $ | |

---

## Step 11: Rent Paid  `[+ Add another]`
**Shown only if** "Did you pay rent in {year}?" = Yes.

| Field | Type | Options |
|---|---|---|
| Landlord's Name | Text | |
| Address of Rented Property * | Text | |
| Postal Code * | Text | |
| Rent Paid * | $ | |
| Is that amount per month or for the whole year? * | Choice | Per month, Total for the year |
| How many months did you stay here in {year}? * | Number | |

---

## Step 12: Work From Home
**Shown only if** "Did you work from home for your employer?" = Yes.

| Field | Type | Options |
|---|---|---|
| Which method would you like to use? | Choice | Flat Rate Method ($2/day), Detailed Method (actual expenses) |
| Do you have a signed T2200 (Declaration of Conditions of Employment) from your employer? | Yes/No | |
| → IF Flat Rate: Number of days worked from home | Number | Max 250 days |
| → IF Detailed: Total home area (sq ft) | Number | |
| → IF Detailed: Office / workspace area (sq ft) | Number | |
| → IF Detailed: Heat · Electricity · Water · Internet · Rent Paid (renters only) · Maintenance & Supplies · Home Insurance · Property Tax · Mortgage Interest (last three: commission employees only) | $ each | |

---

## Step 13: Moving Expenses
**Shown only if** "Did you move to be closer to a new job or school?" = Yes.

**Move Details**
| Field | Type |
|---|---|
| Do you have any unused moving expenses from 2024? | Yes/No |
| → IF Yes: Unused moving expenses from 2024 * | $ |
| Old Street Address * · Old City * · Old Province / Territory * | Text / Text / Dropdown |
| New Street Address * · New City * · New Province / Territory * | Text / Text / Dropdown |
| Distance from old home to new work/school (km) * | Number |
| Distance from new home to new work/school (km) * | Number |
| Move Date * | Date |
| Why did you move? * | Dropdown: Started work at a new location · Started a business at a new location · Full-time student at a post-secondary school |
| When did you start the new job/business/school? * | Date |

**New Work / School Details:** Name * · Street Address * · City * · Province / Territory *

**Travel Summary:** How many people moved? * · Travel method * · Kilometers travelled *

**Moving Costs** ($ unless noted): Transportation and storage (moving belongings) · Travel costs during the move · Temporary living costs · Number of nights (temporary stay) (number) · Meal costs · Number of days for meal costs (number) · Incidental moving costs · Costs to keep old home while vacant · Lease cancellation cost · Did you sell your old home because of this move? (Yes/No) · Selling costs for old home · Reimbursement / allowance you received · Income earned at new work/school location * · Other moving costs

---

## Step 14: Newcomer Info
**Shown only if** "Did you recently move to Canada in {year}?" = Yes.

| Field | Type | Options |
|---|---|---|
| Immigration Type * | Dropdown | Permanent Resident, Work Permit, Study Permit, Refugee, Other |
| Date Entered Canada * | Date | |
| Country of Origin * | Text | |
| Port of Entry | Text | |
| World income earned before arriving in Canada | $ | |
| Currency | Text | |
| Canadian income earned before establishing residency | $ | |

---

## Step 15: Additional Details (always shown)
"Anything else we should know?"
| Field | Type |
|---|---|
| Additional Notes (optional) | Long text, e.g. settlements, unreported side income, unsure expense categories |

---

## Step 16: Documents (always shown)
Upload boxes appear **based on step 3 answers**. Accepted: PDF, JPG, PNG, GIF, WEBP, HEIC, DOC/DOCX, XLS/XLSX, CSV, TXT. Max 10 MB per file.

| Upload | Shown when |
|---|---|
| SIN Document * (required to proceed) | Always |
| Government ID | Always |
| T4 – Pay Slips from Your Employer (multiple) | Worked for an employer |
| T2202 – Tuition Receipt from Your School | Student |
| T4E – Employment Insurance Slip | EI = Yes |
| T4A / T4A(OAS) / T4A(P) – Pension Slips | Pension = Yes |
| T5007 – Social Assistance Slip | Social assistance = Yes |
| T5 / T3 – Investment Slips + T3 – Trust Income Slips | Interest/dividends = Yes |
| T5008 – Securities Transaction Slips + Capital Gains Supporting Documents | Sold stocks/crypto/property = Yes |
| RRSP / FHSA Contribution Receipts | RRSP = Yes |
| Medical & Dental Receipts | Medical = Yes |
| Child Care Receipts | Child care = Yes |
| Donation Receipts | Donations = Yes |
| Property Tax Bill | Property tax = Yes |
| Moving Expense Receipts | Moved for job/school = Yes |
| T2200 – Employer Declaration | Work from home OR employment expenses = Yes |
| T5007 – Workers' Compensation Slip | Workers' comp = Yes |

Plus a catch-all **"Other documents"** uploader with a type dropdown: T4, T4A, T4E, T5, T3, T2202, T5007, T4A(OAS), T4A(P), RL Slip (Quebec), Donation Receipt, Medical Receipt, Rent Receipt, RRSP Receipt, Child Care Receipt, T2200, SIN Document, Government ID, Property Tax Bill, Moving Receipts, Other.

---

## Step 17: Review & Submit (always shown)
- Each section shows: Completed / Not yet filled in / "X required fields missing". You can still submit with gaps.
- **Sign & Submit My Tax Information** opens a signature box with:
  - Declaration checkbox: information is accurate, and client accepts responsibility for reviewing the return and any tax owing.
  - Checkbox agreeing to the firm's Terms & Conditions (limitation of liability, indemnification, client responsibility).
  - **Your Full Legal Name** (typed signature).
- After submitting: "Your tax information has been submitted." plus a **payment link** (Stripe).

---

## Hidden extras (staff/review side only, not shown to clients in the wizard)
The code also has sections their team can fill in on review. Worth considering for your own form:
- **Investment slips:** Slip Type, Institution Name, Interest Income, Eligible Dividends, Capital Gains, Foreign Income, Foreign Tax Paid
- **Capital gains:** Asset Type (Publicly Traded Shares, Mutual Funds, Cryptocurrency, Real Estate, Other), Description, Date Acquired, Date Sold, Proceeds of Disposition, Adjusted Cost Base (ACB), Outlays & Expenses
- **Medical expenses:** Expense Type (Dental, Prescription, Vision, Physiotherapy, Mental Health, Insurance Premiums, Other), Description, Amount, For Whom (Self, Spouse, Dependant)
- **Donations:** Donation Type (Charitable, Political, Cultural/Ecological), Organization, Amount
- **Foreign property (T1135):** Property Type (Foreign Bank Account, Shares, Real Estate, Other), Country, Description, Max Cost During Year, Income Earned
- **Support payments:** Direction (Payments Made / Payments Received), Recipient/Payer Name, Total Amount
- **Disability:** Has Approved DTC (T2201)?, Disability Type, DTC Start Date
- **Northern residents:** Community Name, Prescribed Zone (Zone A / Zone B), Months Lived
- **Employment expenses:** Has T2200?, Employer Name, Accounting/Legal, Food & Beverages, Parking, Supplies, Union Dues, Professional Membership Dues
- **Student:** Full-Time Student?, Months Full-Time, Part-Time Student?, Months Part-Time, Student Loan Interest
- **Direct deposit:** Transit/Branch Number, Institution Number, Account Number


