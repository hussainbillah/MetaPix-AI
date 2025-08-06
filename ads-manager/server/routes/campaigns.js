const express = require('express');
const Joi = require('joi');
const Campaign = require('../models/Campaign');
const { auth } = require('../middleware/auth');
const logger = require('../utils/logger');

const router = express.Router();

// Validation schemas
const createCampaignSchema = Joi.object({
  name: Joi.string().max(100).required(),
  description: Joi.string().max(500).optional(),
  platform: Joi.string().valid(
    'facebook', 'instagram', 'twitter', 'linkedin', 'tiktok', 
    'google', 'youtube', 'pinterest', 'snapchat'
  ).required(),
  objective: Joi.string().valid(
    'awareness', 'traffic', 'engagement', 'leads', 'sales', 'app_installs',
    'video_views', 'reach', 'brand_awareness', 'consideration', 'conversions'
  ).required(),
  budget: Joi.object({
    amount: Joi.number().min(1).required(),
    currency: Joi.string().valid('USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'INR').default('USD'),
    type: Joi.string().valid('daily', 'lifetime').default('daily')
  }).required(),
  schedule: Joi.object({
    startDate: Joi.date().required(),
    endDate: Joi.date().required(),
    timezone: Joi.string().default('UTC')
  }).required(),
  targeting: Joi.object({
    locations: Joi.array().items(Joi.object({
      country: Joi.string(),
      region: Joi.string(),
      city: Joi.string(),
      radius: Joi.number()
    })).optional(),
    ageRange: Joi.object({
      min: Joi.number().min(13).max(65),
      max: Joi.number().min(13).max(65)
    }).optional(),
    gender: Joi.string().valid('all', 'male', 'female', 'other').optional(),
    interests: Joi.array().items(Joi.string()).optional(),
    behaviors: Joi.array().items(Joi.string()).optional(),
    languages: Joi.array().items(Joi.string()).optional(),
    customAudiences: Joi.array().items(Joi.string()).optional(),
    lookalikeAudiences: Joi.array().items(Joi.string()).optional(),
    excludedAudiences: Joi.array().items(Joi.string()).optional()
  }).optional(),
  optimization: Joi.object({
    bidStrategy: Joi.string().valid('lowest_cost', 'cost_cap', 'bid_cap', 'target_cost').default('lowest_cost'),
    bidAmount: Joi.number().min(0.01).optional(),
    optimizationGoal: Joi.string().valid('impressions', 'clicks', 'reach', 'conversions').default('impressions')
  }).optional(),
  tags: Joi.array().items(Joi.string()).optional()
});

const updateCampaignSchema = Joi.object({
  name: Joi.string().max(100).optional(),
  description: Joi.string().max(500).optional(),
  status: Joi.string().valid('draft', 'pending', 'active', 'paused', 'completed', 'cancelled').optional(),
  budget: Joi.object({
    amount: Joi.number().min(1).optional(),
    currency: Joi.string().valid('USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'INR').optional(),
    type: Joi.string().valid('daily', 'lifetime').optional()
  }).optional(),
  schedule: Joi.object({
    startDate: Joi.date().optional(),
    endDate: Joi.date().optional(),
    timezone: Joi.string().optional()
  }).optional(),
  targeting: Joi.object({
    locations: Joi.array().items(Joi.object({
      country: Joi.string(),
      region: Joi.string(),
      city: Joi.string(),
      radius: Joi.number()
    })).optional(),
    ageRange: Joi.object({
      min: Joi.number().min(13).max(65),
      max: Joi.number().min(13).max(65)
    }).optional(),
    gender: Joi.string().valid('all', 'male', 'female', 'other').optional(),
    interests: Joi.array().items(Joi.string()).optional(),
    behaviors: Joi.array().items(Joi.string()).optional(),
    languages: Joi.array().items(Joi.string()).optional(),
    customAudiences: Joi.array().items(Joi.string()).optional(),
    lookalikeAudiences: Joi.array().items(Joi.string()).optional(),
    excludedAudiences: Joi.array().items(Joi.string()).optional()
  }).optional(),
  optimization: Joi.object({
    bidStrategy: Joi.string().valid('lowest_cost', 'cost_cap', 'bid_cap', 'target_cost').optional(),
    bidAmount: Joi.number().min(0.01).optional(),
    optimizationGoal: Joi.string().valid('impressions', 'clicks', 'reach', 'conversions').optional()
  }).optional(),
  tags: Joi.array().items(Joi.string()).optional(),
  notes: Joi.string().optional()
});

// @route   GET /api/campaigns
// @desc    Get all campaigns for current user
// @access  Private
router.get('/', auth, async (req, res) => {
  try {
    const {
      page = 1,
      limit = 10,
      status,
      platform,
      objective,
      sortBy = 'createdAt',
      sortOrder = 'desc',
      search
    } = req.query;

    // Build query
    const query = { user: req.user.id };
    
    if (status) query.status = status;
    if (platform) query.platform = platform;
    if (objective) query.objective = objective;
    if (search) {
      query.$or = [
        { name: { $regex: search, $options: 'i' } },
        { description: { $regex: search, $options: 'i' } }
      ];
    }

    // Build sort object
    const sort = {};
    sort[sortBy] = sortOrder === 'desc' ? -1 : 1;

    // Execute query with pagination
    const campaigns = await Campaign.find(query)
      .sort(sort)
      .limit(limit * 1)
      .skip((page - 1) * limit)
      .populate('user', 'firstName lastName email');

    // Get total count
    const total = await Campaign.countDocuments(query);

    // Calculate pagination info
    const totalPages = Math.ceil(total / limit);
    const hasNextPage = page < totalPages;
    const hasPrevPage = page > 1;

    res.json({
      campaigns,
      pagination: {
        currentPage: parseInt(page),
        totalPages,
        total,
        hasNextPage,
        hasPrevPage,
        limit: parseInt(limit)
      }
    });

  } catch (error) {
    logger.error('Get campaigns error:', error);
    res.status(500).json({ error: 'Server error while fetching campaigns' });
  }
});

// @route   POST /api/campaigns
// @desc    Create a new campaign
// @access  Private
router.post('/', auth, async (req, res) => {
  try {
    // Validate input
    const { error, value } = createCampaignSchema.validate(req.body);
    if (error) {
      return res.status(400).json({ error: error.details[0].message });
    }

    // Check user limits
    const user = await require('../models/User').findById(req.user.id);
    if (!user.checkUsageLimit('campaigns')) {
      return res.status(400).json({ error: 'Campaign limit reached for your plan' });
    }

    // Create campaign
    const campaign = new Campaign({
      ...value,
      user: req.user.id
    });

    await campaign.save();

    // Increment user usage
    await user.incrementUsage('campaigns');

    logger.info(`New campaign created: ${campaign.name} by user: ${req.user.email}`);

    res.status(201).json({
      message: 'Campaign created successfully',
      campaign
    });

  } catch (error) {
    logger.error('Create campaign error:', error);
    res.status(500).json({ error: 'Server error while creating campaign' });
  }
});

// @route   GET /api/campaigns/:id
// @desc    Get specific campaign
// @access  Private
router.get('/:id', auth, async (req, res) => {
  try {
    const { id } = req.params;

    const campaign = await Campaign.findOne({
      _id: id,
      user: req.user.id
    }).populate('user', 'firstName lastName email');

    if (!campaign) {
      return res.status(404).json({ error: 'Campaign not found' });
    }

    res.json({ campaign });

  } catch (error) {
    logger.error('Get campaign error:', error);
    res.status(500).json({ error: 'Server error while fetching campaign' });
  }
});

// @route   PUT /api/campaigns/:id
// @desc    Update campaign
// @access  Private
router.put('/:id', auth, async (req, res) => {
  try {
    const { id } = req.params;

    // Validate input
    const { error, value } = updateCampaignSchema.validate(req.body);
    if (error) {
      return res.status(400).json({ error: error.details[0].message });
    }

    const campaign = await Campaign.findOne({
      _id: id,
      user: req.user.id
    });

    if (!campaign) {
      return res.status(404).json({ error: 'Campaign not found' });
    }

    // Update campaign
    Object.keys(value).forEach(key => {
      campaign[key] = value[key];
    });

    await campaign.save();

    logger.info(`Campaign updated: ${campaign.name} by user: ${req.user.email}`);

    res.json({
      message: 'Campaign updated successfully',
      campaign
    });

  } catch (error) {
    logger.error('Update campaign error:', error);
    res.status(500).json({ error: 'Server error while updating campaign' });
  }
});

// @route   DELETE /api/campaigns/:id
// @desc    Delete campaign
// @access  Private
router.delete('/:id', auth, async (req, res) => {
  try {
    const { id } = req.params;

    const campaign = await Campaign.findOne({
      _id: id,
      user: req.user.id
    });

    if (!campaign) {
      return res.status(404).json({ error: 'Campaign not found' });
    }

    // Check if campaign has active ads
    const Ad = require('../models/Ad');
    const activeAds = await Ad.countDocuments({
      campaign: id,
      status: { $in: ['active', 'pending'] }
    });

    if (activeAds > 0) {
      return res.status(400).json({ 
        error: 'Cannot delete campaign with active ads. Please pause or delete all ads first.' 
      });
    }

    await Campaign.findByIdAndDelete(id);

    logger.info(`Campaign deleted: ${campaign.name} by user: ${req.user.email}`);

    res.json({
      message: 'Campaign deleted successfully'
    });

  } catch (error) {
    logger.error('Delete campaign error:', error);
    res.status(500).json({ error: 'Server error while deleting campaign' });
  }
});

// @route   POST /api/campaigns/:id/duplicate
// @desc    Duplicate campaign
// @access  Private
router.post('/:id/duplicate', auth, async (req, res) => {
  try {
    const { id } = req.params;

    const originalCampaign = await Campaign.findOne({
      _id: id,
      user: req.user.id
    });

    if (!originalCampaign) {
      return res.status(404).json({ error: 'Campaign not found' });
    }

    // Check user limits
    const user = await require('../models/User').findById(req.user.id);
    if (!user.checkUsageLimit('campaigns')) {
      return res.status(400).json({ error: 'Campaign limit reached for your plan' });
    }

    // Create duplicate
    const duplicatedCampaign = new Campaign({
      ...originalCampaign.toObject(),
      _id: undefined,
      name: `${originalCampaign.name} (Copy)`,
      status: 'draft',
      performance: {
        impressions: 0,
        clicks: 0,
        reach: 0,
        frequency: 0,
        ctr: 0,
        cpc: 0,
        cpm: 0,
        conversions: 0,
        conversionRate: 0,
        costPerConversion: 0,
        spend: 0,
        roi: 0
      },
      platformData: {
        status: 'draft'
      }
    });

    await duplicatedCampaign.save();

    // Increment user usage
    await user.incrementUsage('campaigns');

    logger.info(`Campaign duplicated: ${originalCampaign.name} by user: ${req.user.email}`);

    res.status(201).json({
      message: 'Campaign duplicated successfully',
      campaign: duplicatedCampaign
    });

  } catch (error) {
    logger.error('Duplicate campaign error:', error);
    res.status(500).json({ error: 'Server error while duplicating campaign' });
  }
});

// @route   POST /api/campaigns/:id/status
// @desc    Update campaign status
// @access  Private
router.post('/:id/status', auth, async (req, res) => {
  try {
    const { id } = req.params;
    const { status } = req.body;

    if (!['draft', 'pending', 'active', 'paused', 'completed', 'cancelled'].includes(status)) {
      return res.status(400).json({ error: 'Invalid status' });
    }

    const campaign = await Campaign.findOne({
      _id: id,
      user: req.user.id
    });

    if (!campaign) {
      return res.status(404).json({ error: 'Campaign not found' });
    }

    campaign.status = status;
    await campaign.save();

    logger.info(`Campaign status updated: ${campaign.name} to ${status} by user: ${req.user.email}`);

    res.json({
      message: 'Campaign status updated successfully',
      campaign
    });

  } catch (error) {
    logger.error('Update campaign status error:', error);
    res.status(500).json({ error: 'Server error while updating campaign status' });
  }
});

// @route   GET /api/campaigns/stats/overview
// @desc    Get campaign statistics overview
// @access  Private
router.get('/stats/overview', auth, async (req, res) => {
  try {
    const stats = await Campaign.aggregate([
      { $match: { user: require('mongoose').Types.ObjectId(req.user.id) } },
      {
        $group: {
          _id: null,
          total: { $sum: 1 },
          totalSpend: { $sum: '$performance.spend' },
          totalImpressions: { $sum: '$performance.impressions' },
          totalClicks: { $sum: '$performance.clicks' },
          totalConversions: { $sum: '$performance.conversions' },
          avgCtr: { $avg: '$performance.ctr' },
          avgCpc: { $avg: '$performance.cpc' },
          avgCpm: { $avg: '$performance.cpm' }
        }
      }
    ]);

    const statusStats = await Campaign.aggregate([
      { $match: { user: require('mongoose').Types.ObjectId(req.user.id) } },
      {
        $group: {
          _id: '$status',
          count: { $sum: 1 }
        }
      }
    ]);

    const platformStats = await Campaign.aggregate([
      { $match: { user: require('mongoose').Types.ObjectId(req.user.id) } },
      {
        $group: {
          _id: '$platform',
          count: { $sum: 1 },
          spend: { $sum: '$performance.spend' }
        }
      }
    ]);

    res.json({
      overview: stats[0] || {
        total: 0,
        totalSpend: 0,
        totalImpressions: 0,
        totalClicks: 0,
        totalConversions: 0,
        avgCtr: 0,
        avgCpc: 0,
        avgCpm: 0
      },
      statusBreakdown: statusStats,
      platformBreakdown: platformStats
    });

  } catch (error) {
    logger.error('Get campaign stats error:', error);
    res.status(500).json({ error: 'Server error while fetching campaign statistics' });
  }
});

module.exports = router;