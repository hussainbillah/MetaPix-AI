const express = require('express');
const Joi = require('joi');
const Ad = require('../models/Ad');
const Campaign = require('../models/Campaign');
const { auth } = require('../middleware/auth');
const logger = require('../utils/logger');

const router = express.Router();

// Validation schemas
const createAdSchema = Joi.object({
  campaign: Joi.string().required(),
  name: Joi.string().max(100).required(),
  description: Joi.string().max(500).optional(),
  platform: Joi.string().valid(
    'facebook', 'instagram', 'twitter', 'linkedin', 'tiktok', 
    'google', 'youtube', 'pinterest', 'snapchat'
  ).required(),
  adType: Joi.string().valid(
    'image', 'video', 'carousel', 'story', 'reel', 'collection', 'catalog',
    'lead_form', 'messenger', 'app_install', 'dynamic', 'brand_awareness'
  ).required(),
  creative: Joi.object({
    primaryText: Joi.string().max(2000).required(),
    headline: Joi.string().max(40).optional(),
    description: Joi.string().max(125).optional(),
    callToAction: Joi.string().valid(
      'shop_now', 'learn_more', 'sign_up', 'download', 'book_now', 'contact_us',
      'get_quote', 'apply_now', 'subscribe', 'donate_now', 'get_offer'
    ).optional(),
    images: Joi.array().items(Joi.object({
      url: Joi.string().uri().required(),
      altText: Joi.string().optional(),
      width: Joi.number().optional(),
      height: Joi.number().optional(),
      size: Joi.number().optional(),
      format: Joi.string().optional()
    })).optional(),
    videos: Joi.array().items(Joi.object({
      url: Joi.string().uri().required(),
      thumbnail: Joi.string().uri().optional(),
      duration: Joi.number().optional(),
      size: Joi.number().optional(),
      format: Joi.string().optional()
    })).optional(),
    carousel: Joi.array().items(Joi.object({
      title: Joi.string().optional(),
      description: Joi.string().optional(),
      image: Joi.string().uri().optional(),
      link: Joi.string().uri().optional()
    })).optional()
  }).required(),
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
  bidding: Joi.object({
    strategy: Joi.string().valid('lowest_cost', 'cost_cap', 'bid_cap', 'target_cost').default('lowest_cost'),
    bidAmount: Joi.number().min(0.01).optional(),
    optimizationGoal: Joi.string().valid('impressions', 'clicks', 'reach', 'conversions').default('impressions')
  }).optional(),
  tracking: Joi.object({
    pixelId: Joi.string().optional(),
    conversionEvents: Joi.array().items(Joi.string()).optional(),
    customParameters: Joi.array().items(Joi.object({
      key: Joi.string().required(),
      value: Joi.string().required()
    })).optional(),
    utmParameters: Joi.object({
      source: Joi.string().optional(),
      medium: Joi.string().optional(),
      campaign: Joi.string().optional(),
      term: Joi.string().optional(),
      content: Joi.string().optional()
    }).optional()
  }).optional(),
  tags: Joi.array().items(Joi.string()).optional()
});

const updateAdSchema = Joi.object({
  name: Joi.string().max(100).optional(),
  description: Joi.string().max(500).optional(),
  status: Joi.string().valid('draft', 'pending', 'active', 'paused', 'rejected', 'completed', 'cancelled').optional(),
  creative: Joi.object({
    primaryText: Joi.string().max(2000).optional(),
    headline: Joi.string().max(40).optional(),
    description: Joi.string().max(125).optional(),
    callToAction: Joi.string().valid(
      'shop_now', 'learn_more', 'sign_up', 'download', 'book_now', 'contact_us',
      'get_quote', 'apply_now', 'subscribe', 'donate_now', 'get_offer'
    ).optional(),
    images: Joi.array().items(Joi.object({
      url: Joi.string().uri().required(),
      altText: Joi.string().optional(),
      width: Joi.number().optional(),
      height: Joi.number().optional(),
      size: Joi.number().optional(),
      format: Joi.string().optional()
    })).optional(),
    videos: Joi.array().items(Joi.object({
      url: Joi.string().uri().required(),
      thumbnail: Joi.string().uri().optional(),
      duration: Joi.number().optional(),
      size: Joi.number().optional(),
      format: Joi.string().optional()
    })).optional(),
    carousel: Joi.array().items(Joi.object({
      title: Joi.string().optional(),
      description: Joi.string().optional(),
      image: Joi.string().uri().optional(),
      link: Joi.string().uri().optional()
    })).optional()
  }).optional(),
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
  bidding: Joi.object({
    strategy: Joi.string().valid('lowest_cost', 'cost_cap', 'bid_cap', 'target_cost').optional(),
    bidAmount: Joi.number().min(0.01).optional(),
    optimizationGoal: Joi.string().valid('impressions', 'clicks', 'reach', 'conversions').optional()
  }).optional(),
  tracking: Joi.object({
    pixelId: Joi.string().optional(),
    conversionEvents: Joi.array().items(Joi.string()).optional(),
    customParameters: Joi.array().items(Joi.object({
      key: Joi.string().required(),
      value: Joi.string().required()
    })).optional(),
    utmParameters: Joi.object({
      source: Joi.string().optional(),
      medium: Joi.string().optional(),
      campaign: Joi.string().optional(),
      term: Joi.string().optional(),
      content: Joi.string().optional()
    }).optional()
  }).optional(),
  tags: Joi.array().items(Joi.string()).optional(),
  notes: Joi.string().optional()
});

// @route   GET /api/ads
// @desc    Get all ads for current user
// @access  Private
router.get('/', auth, async (req, res) => {
  try {
    const {
      page = 1,
      limit = 10,
      status,
      platform,
      adType,
      campaign,
      sortBy = 'createdAt',
      sortOrder = 'desc',
      search
    } = req.query;

    // Build query
    const query = { user: req.user.id };
    
    if (status) query.status = status;
    if (platform) query.platform = platform;
    if (adType) query.adType = adType;
    if (campaign) query.campaign = campaign;
    if (search) {
      query.$or = [
        { name: { $regex: search, $options: 'i' } },
        { description: { $regex: search, $options: 'i' } },
        { 'creative.primaryText': { $regex: search, $options: 'i' } }
      ];
    }

    // Build sort object
    const sort = {};
    sort[sortBy] = sortOrder === 'desc' ? -1 : 1;

    // Execute query with pagination
    const ads = await Ad.find(query)
      .sort(sort)
      .limit(limit * 1)
      .skip((page - 1) * limit)
      .populate('user', 'firstName lastName email')
      .populate('campaign', 'name platform');

    // Get total count
    const total = await Ad.countDocuments(query);

    // Calculate pagination info
    const totalPages = Math.ceil(total / limit);
    const hasNextPage = page < totalPages;
    const hasPrevPage = page > 1;

    res.json({
      ads,
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
    logger.error('Get ads error:', error);
    res.status(500).json({ error: 'Server error while fetching ads' });
  }
});

// @route   POST /api/ads
// @desc    Create a new ad
// @access  Private
router.post('/', auth, async (req, res) => {
  try {
    // Validate input
    const { error, value } = createAdSchema.validate(req.body);
    if (error) {
      return res.status(400).json({ error: error.details[0].message });
    }

    // Verify campaign exists and belongs to user
    const campaign = await Campaign.findOne({
      _id: value.campaign,
      user: req.user.id
    });

    if (!campaign) {
      return res.status(404).json({ error: 'Campaign not found' });
    }

    // Check user limits
    const user = await require('../models/User').findById(req.user.id);
    if (!user.checkUsageLimit('ads')) {
      return res.status(400).json({ error: 'Ad limit reached for your plan' });
    }

    // Create ad
    const ad = new Ad({
      ...value,
      user: req.user.id
    });

    await ad.save();

    // Increment user usage
    await user.incrementUsage('ads');

    logger.info(`New ad created: ${ad.name} by user: ${req.user.email}`);

    res.status(201).json({
      message: 'Ad created successfully',
      ad
    });

  } catch (error) {
    logger.error('Create ad error:', error);
    res.status(500).json({ error: 'Server error while creating ad' });
  }
});

// @route   GET /api/ads/:id
// @desc    Get specific ad
// @access  Private
router.get('/:id', auth, async (req, res) => {
  try {
    const { id } = req.params;

    const ad = await Ad.findOne({
      _id: id,
      user: req.user.id
    })
    .populate('user', 'firstName lastName email')
    .populate('campaign', 'name platform status');

    if (!ad) {
      return res.status(404).json({ error: 'Ad not found' });
    }

    res.json({ ad });

  } catch (error) {
    logger.error('Get ad error:', error);
    res.status(500).json({ error: 'Server error while fetching ad' });
  }
});

// @route   PUT /api/ads/:id
// @desc    Update ad
// @access  Private
router.put('/:id', auth, async (req, res) => {
  try {
    const { id } = req.params;

    // Validate input
    const { error, value } = updateAdSchema.validate(req.body);
    if (error) {
      return res.status(400).json({ error: error.details[0].message });
    }

    const ad = await Ad.findOne({
      _id: id,
      user: req.user.id
    });

    if (!ad) {
      return res.status(404).json({ error: 'Ad not found' });
    }

    // Update ad
    Object.keys(value).forEach(key => {
      if (key === 'creative') {
        ad.creative = { ...ad.creative, ...value.creative };
      } else {
        ad[key] = value[key];
      }
    });

    await ad.save();

    logger.info(`Ad updated: ${ad.name} by user: ${req.user.email}`);

    res.json({
      message: 'Ad updated successfully',
      ad
    });

  } catch (error) {
    logger.error('Update ad error:', error);
    res.status(500).json({ error: 'Server error while updating ad' });
  }
});

// @route   DELETE /api/ads/:id
// @desc    Delete ad
// @access  Private
router.delete('/:id', auth, async (req, res) => {
  try {
    const { id } = req.params;

    const ad = await Ad.findOne({
      _id: id,
      user: req.user.id
    });

    if (!ad) {
      return res.status(404).json({ error: 'Ad not found' });
    }

    // Check if ad is active
    if (ad.status === 'active' || ad.status === 'pending') {
      return res.status(400).json({ 
        error: 'Cannot delete active or pending ad. Please pause it first.' 
      });
    }

    await Ad.findByIdAndDelete(id);

    logger.info(`Ad deleted: ${ad.name} by user: ${req.user.email}`);

    res.json({
      message: 'Ad deleted successfully'
    });

  } catch (error) {
    logger.error('Delete ad error:', error);
    res.status(500).json({ error: 'Server error while deleting ad' });
  }
});

// @route   POST /api/ads/:id/duplicate
// @desc    Duplicate ad
// @access  Private
router.post('/:id/duplicate', auth, async (req, res) => {
  try {
    const { id } = req.params;

    const originalAd = await Ad.findOne({
      _id: id,
      user: req.user.id
    });

    if (!originalAd) {
      return res.status(404).json({ error: 'Ad not found' });
    }

    // Check user limits
    const user = await require('../models/User').findById(req.user.id);
    if (!user.checkUsageLimit('ads')) {
      return res.status(400).json({ error: 'Ad limit reached for your plan' });
    }

    // Create duplicate
    const duplicatedAd = await originalAd.duplicate();

    // Increment user usage
    await user.incrementUsage('ads');

    logger.info(`Ad duplicated: ${originalAd.name} by user: ${req.user.email}`);

    res.status(201).json({
      message: 'Ad duplicated successfully',
      ad: duplicatedAd
    });

  } catch (error) {
    logger.error('Duplicate ad error:', error);
    res.status(500).json({ error: 'Server error while duplicating ad' });
  }
});

// @route   POST /api/ads/:id/status
// @desc    Update ad status
// @access  Private
router.post('/:id/status', auth, async (req, res) => {
  try {
    const { id } = req.params;
    const { status } = req.body;

    if (!['draft', 'pending', 'active', 'paused', 'rejected', 'completed', 'cancelled'].includes(status)) {
      return res.status(400).json({ error: 'Invalid status' });
    }

    const ad = await Ad.findOne({
      _id: id,
      user: req.user.id
    });

    if (!ad) {
      return res.status(404).json({ error: 'Ad not found' });
    }

    ad.status = status;
    await ad.save();

    logger.info(`Ad status updated: ${ad.name} to ${status} by user: ${req.user.email}`);

    res.json({
      message: 'Ad status updated successfully',
      ad
    });

  } catch (error) {
    logger.error('Update ad status error:', error);
    res.status(500).json({ error: 'Server error while updating ad status' });
  }
});

// @route   PUT /api/ads/:id/performance
// @desc    Update ad performance metrics
// @access  Private
router.put('/:id/performance', auth, async (req, res) => {
  try {
    const { id } = req.params;
    const metrics = req.body;

    const ad = await Ad.findOne({
      _id: id,
      user: req.user.id
    });

    if (!ad) {
      return res.status(404).json({ error: 'Ad not found' });
    }

    // Update performance metrics
    await ad.updatePerformance(metrics);

    logger.info(`Ad performance updated: ${ad.name} by user: ${req.user.email}`);

    res.json({
      message: 'Ad performance updated successfully',
      ad
    });

  } catch (error) {
    logger.error('Update ad performance error:', error);
    res.status(500).json({ error: 'Server error while updating ad performance' });
  }
});

// @route   GET /api/ads/stats/overview
// @desc    Get ad statistics overview
// @access  Private
router.get('/stats/overview', auth, async (req, res) => {
  try {
    const stats = await Ad.aggregate([
      { $match: { user: require('mongoose').Types.ObjectId(req.user.id) } },
      {
        $group: {
          _id: null,
          total: { $sum: 1 },
          totalSpend: { $sum: '$performance.spend' },
          totalImpressions: { $sum: '$performance.impressions' },
          totalClicks: { $sum: '$performance.clicks' },
          totalConversions: { $sum: '$performance.conversions' },
          totalEngagement: { $sum: '$performance.engagement' },
          totalVideoViews: { $sum: '$performance.videoViews' },
          avgCtr: { $avg: '$performance.ctr' },
          avgCpc: { $avg: '$performance.cpc' },
          avgCpm: { $avg: '$performance.cpm' },
          avgEngagementRate: { $avg: '$engagementRate' }
        }
      }
    ]);

    const statusStats = await Ad.aggregate([
      { $match: { user: require('mongoose').Types.ObjectId(req.user.id) } },
      {
        $group: {
          _id: '$status',
          count: { $sum: 1 }
        }
      }
    ]);

    const platformStats = await Ad.aggregate([
      { $match: { user: require('mongoose').Types.ObjectId(req.user.id) } },
      {
        $group: {
          _id: '$platform',
          count: { $sum: 1 },
          spend: { $sum: '$performance.spend' }
        }
      }
    ]);

    const adTypeStats = await Ad.aggregate([
      { $match: { user: require('mongoose').Types.ObjectId(req.user.id) } },
      {
        $group: {
          _id: '$adType',
          count: { $sum: 1 },
          avgEngagement: { $avg: '$performance.engagement' }
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
        totalEngagement: 0,
        totalVideoViews: 0,
        avgCtr: 0,
        avgCpc: 0,
        avgCpm: 0,
        avgEngagementRate: 0
      },
      statusBreakdown: statusStats,
      platformBreakdown: platformStats,
      adTypeBreakdown: adTypeStats
    });

  } catch (error) {
    logger.error('Get ad stats error:', error);
    res.status(500).json({ error: 'Server error while fetching ad statistics' });
  }
});

module.exports = router;