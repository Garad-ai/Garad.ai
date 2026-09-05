# Social Media Agent Demo Data Seeder

This seeder creates fake agents and demo posts for testing the Social Media Agent extension.

## What it creates

### 3 Demo Agents:
1. **TechVision AI - Product Updates**
   - Tech startup focused on AI automation
   - 2 platforms
   - Professional tone
   - 5 demo posts (product updates, tips, engagement, educational)

2. **Luxe Fashion Co. - Spring Collection**
   - Fashion brand with sustainable focus
   - 3 platforms
   - Friendly tone
   - 6 demo posts (promotional, lifestyle, behind-the-scenes)

3. **FitLife Coach - Daily Motivation**
   - Fitness coaching and motivation
   - 2 platforms
   - Enthusiastic tone
   - 5 demo posts (motivational, tips, educational, engagement)

### Post Statuses:
Posts are created with varied statuses to test different scenarios:
- `pending_approval` - Posts waiting for approval
- `approved` - Posts that have been approved
- `scheduled` - Posts scheduled for publishing

## Prerequisites

Before running the seeder, ensure:
1. At least one user exists in the database
2. The user has connected social media platforms (via Social Media extension)

## Usage

Run the seeder using the Artisan command:

```bash
php artisan social-media-agent:seed-demo-data
```

## What the seeder does

1. Finds the first user in the database
2. Gets the user's connected social media platforms
3. Creates 3 diverse agents with different:
   - Business types
   - Target audiences
   - Post types and tones
   - Scheduling preferences
4. Creates 5-6 demo posts for each agent with:
   - Realistic content
   - Hashtags
   - Different statuses
   - Scheduled times spread over the next few days

## Notes

- Posts are created with timestamps spread over the past week (creation date)
- Scheduled times are spread over the next several days
- Each agent uses a subset of the user's available platforms
- The seeder is safe to run multiple times (it will create additional demo data each time)

## Testing Scenarios

This demo data allows you to test:
- ✅ Viewing agents list
- ✅ Viewing pending posts
- ✅ Approving posts
- ✅ Rejecting posts
- ✅ Bulk operations
- ✅ Statistics and analytics
- ✅ Different post types and tones
- ✅ Multi-platform management

## Cleanup

To remove demo data, manually delete agents from the UI or database:
- Delete agents from `ext_social_media_agents` table
- Related posts will be automatically removed via cascade

## Troubleshooting

**Error: "No users found"**
- Solution: Create at least one user in your system first

**Error: "No social media platforms found"**
- Solution: Connect at least one social media platform using the Social Media extension
