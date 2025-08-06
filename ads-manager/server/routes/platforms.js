const express = require('express');
const Joi = require('joi');
const { auth } = require('../middleware/auth');
const logger = require('../utils/logger');

const router = express.Router();

// Validation schemas
const platformConnectionSchema = Joi.object({
  platform: Joi.string().valid(
    'facebook', 'instagram', 'twitter', 'linkedin', 'tiktok', 
    'google', 'youtube', 'pinterest', 'snapchat'
  ).required(),
  accessToken: Joi.string().required(),
  refreshToken: Joi.string().optional(),
  accountId: Joi.string().optional(),
  accountName: Joi.string().optional(),
  permissions: Joi.array().items(Joi.string()).optional(),
  settings: Joi.object({
    autoSync: Joi.boolean().default(true),
    syncInterval: Joi.number().min(300).max(86400).default(3600), // 5 min to 24 hours
    notifications: Joi.boolean().default(true)
  }).optional()
});

const updatePlatformSchema = Joi.object({
  accessToken: Joi.string().optional(),
  refreshToken: Joi.string().optional(),
  accountName: Joi.string().optional(),
  permissions: Joi.array().items(Joi.string()).optional(),
  settings: Joi.object({
    autoSync: Joi.boolean().optional(),
    syncInterval: Joi.number().min(300).max(86400).optional(),
    notifications: Joi.boolean().optional()
  }).optional(),
  isActive: Joi.boolean().optional()
});

// Platform configuration
const platformConfig = {
  facebook: {
    name: 'Facebook',
    apiVersion: 'v18.0',
    baseUrl: 'https://graph.facebook.com',
    scopes: ['ads_management', 'ads_read', 'business_management'],
    features: ['campaigns', 'ads', 'audiences', 'insights', 'pixels'],
    limits: {
      rateLimit: 200,
      timeWindow: 3600
    }
  },
  instagram: {
    name: 'Instagram',
    apiVersion: 'v18.0',
    baseUrl: 'https://graph.facebook.com',
    scopes: ['ads_management', 'ads_read', 'business_management'],
    features: ['campaigns', 'ads', 'audiences', 'insights'],
    limits: {
      rateLimit: 200,
      timeWindow: 3600
    }
  },
  twitter: {
    name: 'Twitter',
    apiVersion: 'v2',
    baseUrl: 'https://api.twitter.com',
    scopes: ['tweet.read', 'tweet.write', 'users.read', 'offline.tweet'],
    features: ['campaigns', 'ads', 'audiences', 'insights'],
    limits: {
      rateLimit: 300,
      timeWindow: 900
    }
  },
  linkedin: {
    name: 'LinkedIn',
    apiVersion: 'v2',
    baseUrl: 'https://api.linkedin.com',
    scopes: ['r_ads', 'rw_ads', 'r_organization_social'],
    features: ['campaigns', 'ads', 'audiences', 'insights'],
    limits: {
      rateLimit: 100,
      timeWindow: 3600
    }
  },
  tiktok: {
    name: 'TikTok',
    apiVersion: 'v1.3',
    baseUrl: 'https://business-api.tiktok.com',
    scopes: ['user.info.basic', 'video.list', 'video.publish'],
    features: ['campaigns', 'ads', 'audiences', 'insights'],
    limits: {
      rateLimit: 100,
      timeWindow: 3600
    }
  },
  google: {
    name: 'Google Ads',
    apiVersion: 'v14',
    baseUrl: 'https://googleads.googleapis.com',
    scopes: ['https://www.googleapis.com/auth/adwords'],
    features: ['campaigns', 'ads', 'audiences', 'insights', 'conversions'],
    limits: {
      rateLimit: 10000,
      timeWindow: 3600
    }
  },
  youtube: {
    name: 'YouTube',
    apiVersion: 'v3',
    baseUrl: 'https://www.googleapis.com/youtube',
    scopes: ['https://www.googleapis.com/auth/youtube.force-ssl'],
    features: ['campaigns', 'ads', 'audiences', 'insights'],
    limits: {
      rateLimit: 10000,
      timeWindow: 3600
    }
  },
  pinterest: {
    name: 'Pinterest',
    apiVersion: 'v5',
    baseUrl: 'https://api.pinterest.com',
    scopes: ['boards:read', 'pins:read', 'pins:write'],
    features: ['campaigns', 'ads', 'audiences', 'insights'],
    limits: {
      rateLimit: 1000,
      timeWindow: 3600
    }
  },
  snapchat: {
    name: 'Snapchat',
    apiVersion: 'v1',
    baseUrl: 'https://kit.snapchat.com',
    scopes: ['snapchat-marketing-api'],
    features: ['campaigns', 'ads', 'audiences', 'insights'],
    limits: {
      rateLimit: 100,
      timeWindow: 3600
    }
  }
};

// @route   GET /api/platforms
// @desc    Get all available platforms
// @access  Private
router.get('/', auth, async (req, res) => {
  try {
    const platforms = Object.keys(platformConfig).map(key => ({
      id: key,
      ...platformConfig[key],
      isConnected: false, // This would be determined by checking user's connections
      lastSync: null,
      status: 'disconnected'
    }));

    res.json({
      platforms,
      total: platforms.length
    });

  } catch (error) {
    logger.error('Get platforms error:', error);
    res.status(500).json({ error: 'Server error while fetching platforms' });
  }
});

// @route   GET /api/platforms/:platform
// @desc    Get specific platform details
// @access  Private
router.get('/:platform', auth, async (req, res) => {
  try {
    const { platform } = req.params;

    if (!platformConfig[platform]) {
      return res.status(404).json({ error: 'Platform not found' });
    }

    const platformInfo = {
      id: platform,
      ...platformConfig[platform],
      isConnected: false, // This would be determined by checking user's connections
      lastSync: null,
      status: 'disconnected'
    };

    res.json(platformInfo);

  } catch (error) {
    logger.error('Get platform error:', error);
    res.status(500).json({ error: 'Server error while fetching platform' });
  }
});

// @route   POST /api/platforms/:platform/connect
// @desc    Connect to a platform
// @access  Private
router.post('/:platform/connect', auth, async (req, res) => {
  try {
    const { platform } = req.params;

    if (!platformConfig[platform]) {
      return res.status(404).json({ error: 'Platform not found' });
    }

    // Validate input
    const { error, value } = platformConnectionSchema.validate(req.body);
    if (error) {
      return res.status(400).json({ error: error.details[0].message });
    }

    // Here you would typically:
    // 1. Validate the access token with the platform
    // 2. Store the connection in the database
    // 3. Set up webhooks or sync schedules

    // For now, we'll simulate a successful connection
    const connection = {
      platform,
      userId: req.user.id,
      accessToken: value.accessToken,
      refreshToken: value.refreshToken,
      accountId: value.accountId,
      accountName: value.accountName,
      permissions: value.permissions || [],
      settings: value.settings || {
        autoSync: true,
        syncInterval: 3600,
        notifications: true
      },
      isActive: true,
      lastSync: new Date(),
      createdAt: new Date()
    };

    logger.info(`Platform connected: ${platform} by user: ${req.user.email}`);

    res.status(201).json({
      message: 'Platform connected successfully',
      connection: {
        platform: connection.platform,
        accountName: connection.accountName,
        permissions: connection.permissions,
        settings: connection.settings,
        isActive: connection.isActive,
        lastSync: connection.lastSync
      }
    });

  } catch (error) {
    logger.error('Connect platform error:', error);
    res.status(500).json({ error: 'Server error while connecting to platform' });
  }
});

// @route   PUT /api/platforms/:platform/disconnect
// @desc    Disconnect from a platform
// @access  Private
router.put('/:platform/disconnect', auth, async (req, res) => {
  try {
    const { platform } = req.params;

    if (!platformConfig[platform]) {
      return res.status(404).json({ error: 'Platform not found' });
    }

    // Here you would typically:
    // 1. Remove the connection from the database
    // 2. Cancel any scheduled syncs
    // 3. Remove webhooks

    logger.info(`Platform disconnected: ${platform} by user: ${req.user.email}`);

    res.json({
      message: 'Platform disconnected successfully'
    });

  } catch (error) {
    logger.error('Disconnect platform error:', error);
    res.status(500).json({ error: 'Server error while disconnecting from platform' });
  }
});

// @route   PUT /api/platforms/:platform/settings
// @desc    Update platform settings
// @access  Private
router.put('/:platform/settings', auth, async (req, res) => {
  try {
    const { platform } = req.params;

    if (!platformConfig[platform]) {
      return res.status(404).json({ error: 'Platform not found' });
    }

    // Validate input
    const { error, value } = updatePlatformSchema.validate(req.body);
    if (error) {
      return res.status(400).json({ error: error.details[0].message });
    }

    // Here you would typically update the connection settings in the database

    logger.info(`Platform settings updated: ${platform} by user: ${req.user.email}`);

    res.json({
      message: 'Platform settings updated successfully',
      settings: value
    });

  } catch (error) {
    logger.error('Update platform settings error:', error);
    res.status(500).json({ error: 'Server error while updating platform settings' });
  }
});

// @route   POST /api/platforms/:platform/sync
// @desc    Manually sync platform data
// @access  Private
router.post('/:platform/sync', auth, async (req, res) => {
  try {
    const { platform } = req.params;
    const { syncType = 'full' } = req.body; // full, campaigns, ads, audiences

    if (!platformConfig[platform]) {
      return res.status(404).json({ error: 'Platform not found' });
    }

    // Here you would typically:
    // 1. Check if the platform is connected
    // 2. Validate the access token
    // 3. Sync data based on syncType
    // 4. Update lastSync timestamp

    // Simulate sync process
    const syncResult = {
      platform,
      syncType,
      status: 'completed',
      startTime: new Date(),
      endTime: new Date(),
      itemsSynced: {
        campaigns: Math.floor(Math.random() * 50),
        ads: Math.floor(Math.random() * 200),
        audiences: Math.floor(Math.random() * 10)
      },
      errors: []
    };

    logger.info(`Platform sync completed: ${platform} by user: ${req.user.email}`);

    res.json({
      message: 'Platform sync completed successfully',
      syncResult
    });

  } catch (error) {
    logger.error('Platform sync error:', error);
    res.status(500).json({ error: 'Server error while syncing platform data' });
  }
});

// @route   GET /api/platforms/:platform/accounts
// @desc    Get connected accounts for a platform
// @access  Private
router.get('/:platform/accounts', auth, async (req, res) => {
  try {
    const { platform } = req.params;

    if (!platformConfig[platform]) {
      return res.status(404).json({ error: 'Platform not found' });
    }

    // Here you would typically fetch connected accounts from the database
    // For now, we'll return mock data
    const accounts = [
      {
        id: 'account_1',
        name: 'Main Business Account',
        type: 'business',
        isActive: true,
        permissions: ['ads_management', 'ads_read'],
        lastSync: new Date()
      }
    ];

    res.json({
      platform,
      accounts,
      total: accounts.length
    });

  } catch (error) {
    logger.error('Get platform accounts error:', error);
    res.status(500).json({ error: 'Server error while fetching platform accounts' });
  }
});

// @route   GET /api/platforms/:platform/insights
// @desc    Get platform insights and limits
// @access  Private
router.get('/:platform/insights', auth, async (req, res) => {
  try {
    const { platform } = req.params;

    if (!platformConfig[platform]) {
      return res.status(404).json({ error: 'Platform not found' });
    }

    const platformInfo = platformConfig[platform];

    // Here you would typically fetch real usage data
    const insights = {
      platform,
      limits: platformInfo.limits,
      currentUsage: {
        rateLimit: Math.floor(Math.random() * platformInfo.limits.rateLimit),
        timeWindow: platformInfo.limits.timeWindow
      },
      features: platformInfo.features,
      lastSync: new Date(),
      connectionStatus: 'active',
      syncStatus: 'up_to_date'
    };

    res.json(insights);

  } catch (error) {
    logger.error('Get platform insights error:', error);
    res.status(500).json({ error: 'Server error while fetching platform insights' });
  }
});

// @route   GET /api/platforms/status/overview
// @desc    Get overview of all platform connections
// @access  Private
router.get('/status/overview', auth, async (req, res) => {
  try {
    // Here you would typically fetch all user's platform connections
    const connections = Object.keys(platformConfig).map(platform => ({
      platform,
      name: platformConfig[platform].name,
      isConnected: Math.random() > 0.5, // Mock data
      lastSync: Math.random() > 0.5 ? new Date() : null,
      status: Math.random() > 0.5 ? 'active' : 'disconnected',
      accountCount: Math.floor(Math.random() * 5) + 1
    }));

    const overview = {
      totalPlatforms: connections.length,
      connectedPlatforms: connections.filter(c => c.isConnected).length,
      activeConnections: connections.filter(c => c.status === 'active').length,
      connections
    };

    res.json(overview);

  } catch (error) {
    logger.error('Get platform status overview error:', error);
    res.status(500).json({ error: 'Server error while fetching platform status overview' });
  }
});

module.exports = router;