const express = require('express');
const Joi = require('joi');
const Campaign = require('../models/Campaign');
const Ad = require('../models/Ad');
const { auth } = require('../middleware/auth');
const logger = require('../utils/logger');

const router = express.Router();

// Validation schemas
const analyticsQuerySchema = Joi.object({
  startDate: Joi.date().optional(),
  endDate: Joi.date().optional(),
  platform: Joi.string().valid(
    'facebook', 'instagram', 'twitter', 'linkedin', 'tiktok', 
    'google', 'youtube', 'pinterest', 'snapchat'
  ).optional(),
  campaign: Joi.string().optional(),
  groupBy: Joi.string().valid('day', 'week', 'month', 'platform', 'campaign', 'adType').default('day'),
  metrics: Joi.array().items(Joi.string().valid(
    'impressions', 'clicks', 'spend', 'ctr', 'cpc', 'cpm', 'conversions', 
    'conversionRate', 'reach', 'frequency', 'engagement', 'videoViews'
  )).optional()
});

// @route   GET /api/analytics/overview
// @desc    Get analytics overview
// @access  Private
router.get('/overview', auth, async (req, res) => {
  try {
    const { startDate, endDate, platform } = req.query;

    // Build date filter
    const dateFilter = {};
    if (startDate) dateFilter.$gte = new Date(startDate);
    if (endDate) dateFilter.$lte = new Date(endDate);

    // Build query
    const query = { user: req.user.id };
    if (platform) query.platform = platform;
    if (Object.keys(dateFilter).length > 0) {
      query['schedule.startDate'] = dateFilter;
    }

    // Get campaign stats
    const campaignStats = await Campaign.aggregate([
      { $match: query },
      {
        $group: {
          _id: null,
          totalCampaigns: { $sum: 1 },
          activeCampaigns: {
            $sum: { $cond: [{ $eq: ['$status', 'active'] }, 1, 0] }
          },
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

    // Get ad stats
    const adStats = await Ad.aggregate([
      { $match: query },
      {
        $group: {
          _id: null,
          totalAds: { $sum: 1 },
          activeAds: {
            $sum: { $cond: [{ $eq: ['$status', 'active'] }, 1, 0] }
          },
          totalAdSpend: { $sum: '$performance.spend' },
          totalAdImpressions: { $sum: '$performance.impressions' },
          totalAdClicks: { $sum: '$performance.clicks' },
          totalAdConversions: { $sum: '$performance.conversions' },
          totalEngagement: { $sum: '$performance.engagement' },
          totalVideoViews: { $sum: '$performance.videoViews' },
          avgAdCtr: { $avg: '$performance.ctr' },
          avgAdCpc: { $avg: '$performance.cpc' },
          avgAdCpm: { $avg: '$performance.cpm' }
        }
      }
    ]);

    // Get platform breakdown
    const platformBreakdown = await Campaign.aggregate([
      { $match: query },
      {
        $group: {
          _id: '$platform',
          campaigns: { $sum: 1 },
          spend: { $sum: '$performance.spend' },
          impressions: { $sum: '$performance.impressions' },
          clicks: { $sum: '$performance.clicks' },
          conversions: { $sum: '$performance.conversions' }
        }
      },
      { $sort: { spend: -1 } }
    ]);

    // Get recent performance (last 7 days)
    const sevenDaysAgo = new Date();
    sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);

    const recentPerformance = await Campaign.aggregate([
      {
        $match: {
          ...query,
          'schedule.startDate': { $gte: sevenDaysAgo }
        }
      },
      {
        $group: {
          _id: null,
          recentSpend: { $sum: '$performance.spend' },
          recentImpressions: { $sum: '$performance.impressions' },
          recentClicks: { $sum: '$performance.clicks' },
          recentConversions: { $sum: '$performance.conversions' }
        }
      }
    ]);

    const overview = {
      campaigns: campaignStats[0] || {
        totalCampaigns: 0,
        activeCampaigns: 0,
        totalSpend: 0,
        totalImpressions: 0,
        totalClicks: 0,
        totalConversions: 0,
        avgCtr: 0,
        avgCpc: 0,
        avgCpm: 0
      },
      ads: adStats[0] || {
        totalAds: 0,
        activeAds: 0,
        totalAdSpend: 0,
        totalAdImpressions: 0,
        totalAdClicks: 0,
        totalAdConversions: 0,
        totalEngagement: 0,
        totalVideoViews: 0,
        avgAdCtr: 0,
        avgAdCpc: 0,
        avgAdCpm: 0
      },
      platformBreakdown,
      recentPerformance: recentPerformance[0] || {
        recentSpend: 0,
        recentImpressions: 0,
        recentClicks: 0,
        recentConversions: 0
      }
    };

    res.json(overview);

  } catch (error) {
    logger.error('Get analytics overview error:', error);
    res.status(500).json({ error: 'Server error while fetching analytics overview' });
  }
});

// @route   GET /api/analytics/performance
// @desc    Get performance analytics with time series data
// @access  Private
router.get('/performance', auth, async (req, res) => {
  try {
    const { error, value } = analyticsQuerySchema.validate(req.query);
    if (error) {
      return res.status(400).json({ error: error.details[0].message });
    }

    const { startDate, endDate, platform, campaign, groupBy, metrics = ['impressions', 'clicks', 'spend'] } = value;

    // Build date filter
    const dateFilter = {};
    if (startDate) dateFilter.$gte = new Date(startDate);
    if (endDate) dateFilter.$lte = new Date(endDate);

    // Build query
    const query = { user: req.user.id };
    if (platform) query.platform = platform;
    if (campaign) query.campaign = campaign;
    if (Object.keys(dateFilter).length > 0) {
      query['schedule.startDate'] = dateFilter;
    }

    let aggregationPipeline = [{ $match: query }];

    // Add grouping based on groupBy parameter
    if (groupBy === 'day') {
      aggregationPipeline.push({
        $group: {
          _id: {
            $dateToString: { format: '%Y-%m-%d', date: '$schedule.startDate' }
          },
          impressions: { $sum: '$performance.impressions' },
          clicks: { $sum: '$performance.clicks' },
          spend: { $sum: '$performance.spend' },
          conversions: { $sum: '$performance.conversions' },
          reach: { $sum: '$performance.reach' },
          engagement: { $sum: '$performance.engagement' },
          videoViews: { $sum: '$performance.videoViews' }
        }
      });
    } else if (groupBy === 'week') {
      aggregationPipeline.push({
        $group: {
          _id: {
            year: { $year: '$schedule.startDate' },
            week: { $week: '$schedule.startDate' }
          },
          impressions: { $sum: '$performance.impressions' },
          clicks: { $sum: '$performance.clicks' },
          spend: { $sum: '$performance.spend' },
          conversions: { $sum: '$performance.conversions' },
          reach: { $sum: '$performance.reach' },
          engagement: { $sum: '$performance.engagement' },
          videoViews: { $sum: '$performance.videoViews' }
        }
      });
    } else if (groupBy === 'month') {
      aggregationPipeline.push({
        $group: {
          _id: {
            year: { $year: '$schedule.startDate' },
            month: { $month: '$schedule.startDate' }
          },
          impressions: { $sum: '$performance.impressions' },
          clicks: { $sum: '$performance.clicks' },
          spend: { $sum: '$performance.spend' },
          conversions: { $sum: '$performance.conversions' },
          reach: { $sum: '$performance.reach' },
          engagement: { $sum: '$performance.engagement' },
          videoViews: { $sum: '$performance.videoViews' }
        }
      });
    } else if (groupBy === 'platform') {
      aggregationPipeline.push({
        $group: {
          _id: '$platform',
          impressions: { $sum: '$performance.impressions' },
          clicks: { $sum: '$performance.clicks' },
          spend: { $sum: '$performance.spend' },
          conversions: { $sum: '$performance.conversions' },
          reach: { $sum: '$performance.reach' },
          engagement: { $sum: '$performance.engagement' },
          videoViews: { $sum: '$performance.videoViews' }
        }
      });
    } else if (groupBy === 'campaign') {
      aggregationPipeline.push({
        $group: {
          _id: '$campaign',
          impressions: { $sum: '$performance.impressions' },
          clicks: { $sum: '$performance.clicks' },
          spend: { $sum: '$performance.spend' },
          conversions: { $sum: '$performance.conversions' },
          reach: { $sum: '$performance.reach' },
          engagement: { $sum: '$performance.engagement' },
          videoViews: { $sum: '$performance.videoViews' }
        }
      });
    } else if (groupBy === 'adType') {
      aggregationPipeline.push({
        $group: {
          _id: '$adType',
          impressions: { $sum: '$performance.impressions' },
          clicks: { $sum: '$performance.clicks' },
          spend: { $sum: '$performance.spend' },
          conversions: { $sum: '$performance.conversions' },
          reach: { $sum: '$performance.reach' },
          engagement: { $sum: '$performance.engagement' },
          videoViews: { $sum: '$performance.videoViews' }
        }
      });
    }

    // Add sorting
    aggregationPipeline.push({ $sort: { _id: 1 } });

    const performanceData = await Campaign.aggregate(aggregationPipeline);

    // Calculate derived metrics
    const processedData = performanceData.map(item => {
      const processed = { ...item };
      
      if (metrics.includes('ctr') && item.impressions > 0) {
        processed.ctr = (item.clicks / item.impressions) * 100;
      }
      
      if (metrics.includes('cpc') && item.clicks > 0) {
        processed.cpc = item.spend / item.clicks;
      }
      
      if (metrics.includes('cpm') && item.impressions > 0) {
        processed.cpm = (item.spend / item.impressions) * 1000;
      }
      
      if (metrics.includes('conversionRate') && item.clicks > 0) {
        processed.conversionRate = (item.conversions / item.clicks) * 100;
      }
      
      if (metrics.includes('engagementRate') && item.impressions > 0) {
        processed.engagementRate = (item.engagement / item.impressions) * 100;
      }
      
      return processed;
    });

    res.json({
      performanceData: processedData,
      metrics,
      groupBy,
      filters: { startDate, endDate, platform, campaign }
    });

  } catch (error) {
    logger.error('Get performance analytics error:', error);
    res.status(500).json({ error: 'Server error while fetching performance analytics' });
  }
});

// @route   GET /api/analytics/top-performers
// @desc    Get top performing campaigns and ads
// @access  Private
router.get('/top-performers', auth, async (req, res) => {
  try {
    const { startDate, endDate, platform, limit = 10 } = req.query;

    // Build date filter
    const dateFilter = {};
    if (startDate) dateFilter.$gte = new Date(startDate);
    if (endDate) dateFilter.$lte = new Date(endDate);

    // Build query
    const query = { user: req.user.id };
    if (platform) query.platform = platform;
    if (Object.keys(dateFilter).length > 0) {
      query['schedule.startDate'] = dateFilter;
    }

    // Get top campaigns by spend
    const topCampaignsBySpend = await Campaign.find(query)
      .sort({ 'performance.spend': -1 })
      .limit(parseInt(limit))
      .populate('user', 'firstName lastName email');

    // Get top campaigns by CTR
    const topCampaignsByCtr = await Campaign.find({
      ...query,
      'performance.impressions': { $gt: 0 }
    })
      .sort({ 'performance.ctr': -1 })
      .limit(parseInt(limit))
      .populate('user', 'firstName lastName email');

    // Get top campaigns by conversions
    const topCampaignsByConversions = await Campaign.find(query)
      .sort({ 'performance.conversions': -1 })
      .limit(parseInt(limit))
      .populate('user', 'firstName lastName email');

    // Get top ads by spend
    const topAdsBySpend = await Ad.find(query)
      .sort({ 'performance.spend': -1 })
      .limit(parseInt(limit))
      .populate('campaign', 'name platform')
      .populate('user', 'firstName lastName email');

    // Get top ads by engagement
    const topAdsByEngagement = await Ad.find({
      ...query,
      'performance.impressions': { $gt: 0 }
    })
      .sort({ 'performance.engagement': -1 })
      .limit(parseInt(limit))
      .populate('campaign', 'name platform')
      .populate('user', 'firstName lastName email');

    res.json({
      topCampaignsBySpend,
      topCampaignsByCtr,
      topCampaignsByConversions,
      topAdsBySpend,
      topAdsByEngagement
    });

  } catch (error) {
    logger.error('Get top performers error:', error);
    res.status(500).json({ error: 'Server error while fetching top performers' });
  }
});

// @route   GET /api/analytics/trends
// @desc    Get performance trends over time
// @access  Private
router.get('/trends', auth, async (req, res) => {
  try {
    const { days = 30, platform } = req.query;

    const startDate = new Date();
    startDate.setDate(startDate.getDate() - parseInt(days));

    // Build query
    const query = { 
      user: req.user.id,
      'schedule.startDate': { $gte: startDate }
    };
    if (platform) query.platform = platform;

    // Get daily trends
    const dailyTrends = await Campaign.aggregate([
      { $match: query },
      {
        $group: {
          _id: {
            $dateToString: { format: '%Y-%m-%d', date: '$schedule.startDate' }
          },
          campaigns: { $sum: 1 },
          spend: { $sum: '$performance.spend' },
          impressions: { $sum: '$performance.impressions' },
          clicks: { $sum: '$performance.clicks' },
          conversions: { $sum: '$performance.conversions' },
          reach: { $sum: '$performance.reach' }
        }
      },
      { $sort: { _id: 1 } }
    ]);

    // Get weekly trends
    const weeklyTrends = await Campaign.aggregate([
      { $match: query },
      {
        $group: {
          _id: {
            year: { $year: '$schedule.startDate' },
            week: { $week: '$schedule.startDate' }
          },
          campaigns: { $sum: 1 },
          spend: { $sum: '$performance.spend' },
          impressions: { $sum: '$performance.impressions' },
          clicks: { $sum: '$performance.clicks' },
          conversions: { $sum: '$performance.conversions' },
          reach: { $sum: '$performance.reach' }
        }
      },
      { $sort: { '_id.year': 1, '_id.week': 1 } }
    ]);

    // Calculate growth rates
    const calculateGrowthRate = (data, metric) => {
      const growthRates = [];
      for (let i = 1; i < data.length; i++) {
        const current = data[i][metric] || 0;
        const previous = data[i - 1][metric] || 0;
        const growthRate = previous > 0 ? ((current - previous) / previous) * 100 : 0;
        growthRates.push({
          date: data[i]._id,
          growthRate: Math.round(growthRate * 100) / 100
        });
      }
      return growthRates;
    };

    const spendGrowth = calculateGrowthRate(dailyTrends, 'spend');
    const impressionsGrowth = calculateGrowthRate(dailyTrends, 'impressions');
    const clicksGrowth = calculateGrowthRate(dailyTrends, 'clicks');

    res.json({
      dailyTrends,
      weeklyTrends,
      growthRates: {
        spend: spendGrowth,
        impressions: impressionsGrowth,
        clicks: clicksGrowth
      }
    });

  } catch (error) {
    logger.error('Get trends error:', error);
    res.status(500).json({ error: 'Server error while fetching trends' });
  }
});

// @route   GET /api/analytics/export
// @desc    Export analytics data
// @access  Private
router.get('/export', auth, async (req, res) => {
  try {
    const { startDate, endDate, platform, format = 'json' } = req.query;

    // Build date filter
    const dateFilter = {};
    if (startDate) dateFilter.$gte = new Date(startDate);
    if (endDate) dateFilter.$lte = new Date(endDate);

    // Build query
    const query = { user: req.user.id };
    if (platform) query.platform = platform;
    if (Object.keys(dateFilter).length > 0) {
      query['schedule.startDate'] = dateFilter;
    }

    // Get campaigns data
    const campaigns = await Campaign.find(query)
      .populate('user', 'firstName lastName email');

    // Get ads data
    const ads = await Ad.find(query)
      .populate('campaign', 'name platform')
      .populate('user', 'firstName lastName email');

    const exportData = {
      exportDate: new Date().toISOString(),
      filters: { startDate, endDate, platform },
      campaigns: campaigns.length,
      ads: ads.length,
      data: {
        campaigns,
        ads
      }
    };

    if (format === 'csv') {
      // Convert to CSV format
      const csvData = convertToCSV(exportData);
      res.setHeader('Content-Type', 'text/csv');
      res.setHeader('Content-Disposition', 'attachment; filename=analytics-export.csv');
      res.send(csvData);
    } else {
      res.json(exportData);
    }

  } catch (error) {
    logger.error('Export analytics error:', error);
    res.status(500).json({ error: 'Server error while exporting analytics' });
  }
});

// Helper function to convert data to CSV
const convertToCSV = (data) => {
  // Implementation for CSV conversion
  // This is a simplified version - you might want to use a library like 'json2csv'
  return JSON.stringify(data);
};

module.exports = router;