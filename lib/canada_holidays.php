<?php

declare(strict_types=1);

/**
 * Build a full HTML holiday greeting email (Verma Accounting style).
 *
 * @param array{title?: string, occasion?: string, message?: string, appreciation?: string, image_url?: string, signoff?: string} $opts
 */
function canada_holiday_email_html(array $opts): string
{
    $occasion = trim((string) ($opts['occasion'] ?? 'this special day'));
    $message = trim((string) ($opts['message'] ?? ''));
    $appreciation = trim((string) ($opts['appreciation'] ?? ''));
    $imageUrl = trim((string) ($opts['image_url'] ?? ''));
    $signoff = trim((string) ($opts['signoff'] ?? 'Warm regards,'));

    if ($message === '') {
        $message = 'On this occasion of ' . $occasion . ', we extend our heartfelt wishes to you and your loved ones. '
            . 'May your days be filled with peace, your heart with gratitude, and your life with continued success and happiness.';
    }
    if ($appreciation === '') {
        $appreciation = 'We sincerely appreciate your continued trust and partnership. It is always our pleasure to serve you, '
            . 'and we look forward to achieving more together in the days ahead.';
    }

    $imageBlock = '';
    if ($imageUrl !== '') {
        $imageBlock = '
<tr>
    <td align="center" style="padding:20px 40px;">
       <img src="' . htmlspecialchars($imageUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '" alt="" style="width:100%; max-width:520px; border-radius:10px;">
    </td>
</tr>';
    }

    $siteUrl = app_base_url() ?: 'https://vermaaccounting.ca';
    $logoHtml = brand_logo_email_html();
    $companyName = 'Verma Accounting &amp; Financial Services';

    return '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>' . htmlspecialchars((string) ($opts['title'] ?? $occasion), ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</title>
</head>
<body style="margin:0; padding:0; background:#f1f5f9; font-family: Arial, sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td align="center">
      <table width="650" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:12px; overflow:hidden;">
        <tr>
          <td align="center" style="padding:28px 40px 18px 40px; background:#ffffff; border-bottom:1px solid #e2e8f0;">
            <a href="' . htmlspecialchars($siteUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '" style="text-decoration:none; display:inline-block;">
              ' . $logoHtml . '
            </a>
            <p style="margin:12px 0 0 0; font-size:15px; font-weight:700; color:#1e3a8a; letter-spacing:0.01em;">
              ' . $companyName . '
            </p>
          </td>
        </tr>
        <tr>
          <td style="padding:24px 40px 0 40px; color:#1e293b;">
            <p style="font-size:16px;">Dear <strong>{client_name}</strong>,</p>
          </td>
        </tr>
        <tr>
          <td style="padding:10px 40px 20px 40px; color:#334155;">
            <p style="font-size:15px; line-height:1.8;">
              ' . htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '
            </p>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:10px 40px;">
            <div style="height:2px; background:linear-gradient(to right, transparent, #f97316, transparent);"></div>
          </td>
        </tr>
        ' . $imageBlock . '
        <tr>
          <td style="padding:10px 40px 20px 40px; color:#334155;">
            <p style="font-size:15px; line-height:1.8;">
              ' . htmlspecialchars($appreciation, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '
            </p>
          </td>
        </tr>
        <tr>
          <td style="padding:0 40px 30px 40px;">
            <p style="margin:0;">' . htmlspecialchars($signoff, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>
            <p style="margin:5px 0 0 0; font-weight:bold; font-size:16px; color:#1e3a8a;">
              ' . $companyName . '
            </p>
          </td>
        </tr>
        <tr>
          <td style="background:#0f172a; color:#ffffff; padding:25px; text-align:center;">
            <p style="margin:5px 0; font-size:13px;">© {year} ' . $companyName . '</p>
            <p style="margin:5px 0; font-size:12px; color:#94a3b8;">
              All rights reserved.
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>';
}

/**
 * Canadian / Ontario holiday email definitions.
 *
 * date_rule:
 * - "" fixed month/day
 * - "nth_weekday:{weekday}:{month}:{nth}" weekday 1=Mon..7=Sun, nth 1-5 or -1 (last)
 * - "victoria_day"
 * - "easter:{offset}" days from Easter Sunday (Good Friday = -2)
 *
 * @return list<array{
 *   name: string,
 *   holiday_month: int,
 *   holiday_day: int,
 *   date_rule: string,
 *   send_time: string,
 *   subject: string,
 *   body_html: string,
 *   enabled: int
 * }>
 */
function canada_holiday_email_definitions(): array
{
    $eidImage = 'https://vermaaccounting.ca/email-asset/f04f839ffb7297396d679499ccf4343c.jpg';

    $defs = [
        [
            'name' => "New Year's Day",
            'holiday_month' => 1,
            'holiday_day' => 1,
            'date_rule' => '',
            'subject' => 'Happy New Year from Verma Accounting',
            'occasion' => "New Year's Day",
            'message' => "As we welcome the New Year, we extend our warmest wishes to you and your loved ones. May the year ahead bring good health, prosperity, and success in all your endeavours.",
        ],
        [
            'name' => 'Family Day',
            'holiday_month' => 2,
            'holiday_day' => 15,
            'date_rule' => 'nth_weekday:1:2:3',
            'subject' => 'Happy Family Day from Verma Accounting',
            'occasion' => 'Family Day',
            'message' => 'On this Family Day, we hope you enjoy meaningful time with the people who matter most. Wishing you rest, connection, and lasting memories.',
        ],
        [
            'name' => 'Good Friday',
            'holiday_month' => 4,
            'holiday_day' => 1,
            'date_rule' => 'easter:-2',
            'subject' => 'Warm wishes this Good Friday — Verma Accounting',
            'occasion' => 'Good Friday',
            'message' => 'On this Good Friday, we send our sincere wishes for peace and reflection. May you and your family find comfort and renewed hope this season.',
        ],
        [
            'name' => 'Victoria Day',
            'holiday_month' => 5,
            'holiday_day' => 24,
            'date_rule' => 'victoria_day',
            'subject' => 'Happy Victoria Day from Verma Accounting',
            'occasion' => 'Victoria Day',
            'message' => 'Wishing you a wonderful Victoria Day long weekend. We hope you enjoy a well-deserved break and time outdoors with family and friends.',
        ],
        [
            'name' => 'Canada Day',
            'holiday_month' => 7,
            'holiday_day' => 1,
            'date_rule' => '',
            'subject' => 'Happy Canada Day from Verma Accounting',
            'occasion' => 'Canada Day',
            'message' => 'Happy Canada Day! We are proud to celebrate this great country with you. Wishing you a joyful day filled with community, gratitude, and Canadian pride.',
        ],
        [
            'name' => 'Civic Holiday',
            'holiday_month' => 8,
            'holiday_day' => 1,
            'date_rule' => 'nth_weekday:1:8:1',
            'subject' => 'Happy Civic Holiday from Verma Accounting',
            'occasion' => 'the Civic Holiday',
            'message' => 'Wishing you a relaxing Civic Holiday. We hope you enjoy the long weekend and return refreshed for the months ahead.',
        ],
        [
            'name' => 'Labour Day',
            'holiday_month' => 9,
            'holiday_day' => 1,
            'date_rule' => 'nth_weekday:1:9:1',
            'subject' => 'Happy Labour Day from Verma Accounting',
            'occasion' => 'Labour Day',
            'message' => 'On Labour Day, we celebrate the dedication and hard work that keep our communities thriving. Wishing you a restful long weekend with family and friends.',
        ],
        [
            'name' => 'Thanksgiving',
            'holiday_month' => 10,
            'holiday_day' => 10,
            'date_rule' => 'nth_weekday:1:10:2',
            'subject' => 'Happy Thanksgiving from Verma Accounting',
            'occasion' => 'Thanksgiving',
            'message' => 'This Thanksgiving, we are grateful for your trust and partnership. Wishing you a warm holiday filled with family, friendship, and appreciation.',
        ],
        [
            'name' => 'Remembrance Day',
            'holiday_month' => 11,
            'holiday_day' => 11,
            'date_rule' => '',
            'subject' => 'Remembering with gratitude — Verma Accounting',
            'occasion' => 'Remembrance Day',
            'message' => 'On Remembrance Day, we pause to honour those who served and sacrificed for our freedom. We hold their memory with deep respect and gratitude.',
        ],
        [
            'name' => 'Christmas Day',
            'holiday_month' => 12,
            'holiday_day' => 25,
            'date_rule' => '',
            'subject' => 'Merry Christmas from Verma Accounting',
            'occasion' => 'Christmas',
            'message' => 'Merry Christmas! May this festive season bring joy, warmth, and precious moments with your loved ones. Wishing you peace and happiness throughout the holidays.',
        ],
        [
            'name' => 'Boxing Day',
            'holiday_month' => 12,
            'holiday_day' => 26,
            'date_rule' => '',
            'subject' => 'Happy Boxing Day from Verma Accounting',
            'occasion' => 'Boxing Day',
            'message' => 'Wishing you a Happy Boxing Day! We hope you continue to enjoy the holiday season with rest, celebration, and time well spent with those closest to you.',
        ],
        [
            'name' => 'Eid ul-Fitr',
            'holiday_month' => 3,
            'holiday_day' => 20,
            'date_rule' => '',
            'subject' => 'Eid Mubarak from Verma Accounting',
            'occasion' => 'Eid ul-Fitr',
            'message' => 'On this blessed occasion of Eid ul-Fitr, we extend our heartfelt wishes to you and your loved ones. May your days be filled with peace, your heart with gratitude, and your life with continued success and happiness.',
            'image_url' => $eidImage,
            'enabled' => 0,
        ],
        [
            'name' => 'Eid ul-Adha',
            'holiday_month' => 5,
            'holiday_day' => 27,
            'date_rule' => '',
            'subject' => 'Eid ul-Adha Mubarak from Verma Accounting',
            'occasion' => 'Eid ul-Adha',
            'message' => 'On this blessed occasion of Eid ul-Adha, we extend our heartfelt wishes to you and your loved ones. May this sacred time bring peace, compassion, and abundant blessings to you and your family.',
            'image_url' => $eidImage,
            'enabled' => 0,
        ],
        [
            'name' => 'Diwali',
            'holiday_month' => 11,
            'holiday_day' => 8,
            'date_rule' => '',
            'subject' => 'Happy Diwali from Verma Accounting',
            'occasion' => 'Diwali',
            'message' => 'On this joyous Festival of Lights, we wish you and your loved ones a Happy Diwali. May your home be filled with light, prosperity, and happiness, and may the year ahead bring continued success and well-being.',
            'enabled' => 0,
        ],
    ];

    $out = [];
    foreach ($defs as $def) {
        $out[] = [
            'name' => $def['name'],
            'holiday_month' => (int) $def['holiday_month'],
            'holiday_day' => (int) $def['holiday_day'],
            'date_rule' => (string) ($def['date_rule'] ?? ''),
            'send_time' => '09:00:00',
            'subject' => (string) $def['subject'],
            'body_html' => canada_holiday_email_html([
                'title' => (string) $def['subject'],
                'occasion' => (string) $def['occasion'],
                'message' => (string) $def['message'],
                'image_url' => (string) ($def['image_url'] ?? ''),
            ]),
            'enabled' => array_key_exists('enabled', $def) ? (int) $def['enabled'] : 1,
        ];
    }

    return $out;
}

/**
 * Upsert Canada holiday email templates by name.
 *
 * @return array{created: int, updated: int, total: int}
 */
function seed_canada_holiday_emails(?string $createdByName = 'Canada holidays seed'): array
{
    $repo = new HolidayScheduleRepository();
    $existingByName = [];
    foreach ($repo->all() as $row) {
        $key = strtolower(trim((string) ($row['name'] ?? '')));
        if ($key !== '') {
            $existingByName[$key] = $row;
        }
    }

    $created = 0;
    $updated = 0;

    foreach (canada_holiday_email_definitions() as $def) {
        $key = strtolower($def['name']);
        $payload = [
            'name' => $def['name'],
            'holiday_month' => $def['holiday_month'],
            'holiday_day' => $def['holiday_day'],
            'date_rule' => $def['date_rule'],
            'send_time' => $def['send_time'],
            'subject' => $def['subject'],
            'body_html' => $def['body_html'],
            'enabled' => $def['enabled'],
        ];

        if (isset($existingByName[$key])) {
            $id = (int) $existingByName[$key]['id'];
            // Keep admin enable/pause preference on update; refresh content + schedule rule.
            $payload['enabled'] = !empty($existingByName[$key]['enabled']) ? 1 : 0;
            $repo->update($id, $payload);
            $updated++;
            continue;
        }

        $payload['created_by_name'] = $createdByName ?? 'Canada holidays seed';
        $repo->create($payload);
        $created++;
    }

    return [
        'created' => $created,
        'updated' => $updated,
        'total' => $created + $updated,
    ];
}
