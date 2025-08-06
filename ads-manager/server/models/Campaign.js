const mongoose = require('mongoose');

const campaignSchema = new mongoose.Schema({
  user: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User',
    required: true
  },
  name: {
    type: String,
    required: [true, 'Campaign name is required'],
    trim: true,
    maxlength: [100, 'Campaign name cannot exceed 100 characters']
  },
  description: {
    type: String,
    trim: true,
    maxlength: [500, 'Description cannot exceed 500 characters']
  },
  platform: {
    type: String,
    required: [true, 'Platform is required'],
    enum: ['facebook', 'instagram', 'twitter', 'linkedin', 'tiktok', 'google', 'youtube', 'pinterest', 'snapchat']
  },
  status: {
    type: String,
    enum: ['draft', 'pending', 'active', 'paused', 'completed', 'cancelled'],
    default: 'draft'
  },
  objective: {
    type: String,
    enum: [
      'awareness', 'traffic', 'engagement', 'leads', 'sales', 'app_installs',
      'video_views', 'reach', 'brand_awareness', 'consideration', 'conversions'
    ],
    required: [true, 'Campaign objective is required']
  },
  budget: {
    amount: {
      type: Number,
      required: [true, 'Budget amount is required'],
      min: [1, 'Budget must be at least 1']
    },
    currency: {
      type: String,
      default: 'USD',
      enum: ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'INR']
    },
    type: {
      type: String,
      enum: ['daily', 'lifetime'],
      default: 'daily'
    },
    spent: {
      type: Number,
      default: 0
    }
  },
  schedule: {
    startDate: {
      type: Date,
      required: [true, 'Start date is required']
    },
    endDate: {
      type: Date,
      required: [true, 'End date is required']
    },
    timezone: {
      type: String,
      default: 'UTC'
    }
  },
  targeting: {
    locations: [{
      country: String,
      region: String,
      city: String,
      radius: Number
    }],
    ageRange: {
      min: { type: Number, min: 13, max: 65 },
      max: { type: Number, min: 13, max: 65 }
    },
    gender: {
      type: String,
      enum: ['all', 'male', 'female', 'other']
    },
    interests: [String],
    behaviors: [String],
    languages: [String],
    customAudiences: [String],
    lookalikeAudiences: [String],
    excludedAudiences: [String]
  },
  adPlacements: {
    facebook: {
      feed: { type: Boolean, default: true },
      stories: { type: Boolean, default: false },
      reels: { type: Boolean, default: false },
      marketplace: { type: Boolean, default: false },
      instagram: { type: Boolean, default: false }
    },
    instagram: {
      feed: { type: Boolean, default: true },
      stories: { type: Boolean, default: false },
      reels: { type: Boolean, default: false },
      explore: { type: Boolean, default: false }
    },
    twitter: {
      timeline: { type: Boolean, default: true },
      search: { type: Boolean, default: false },
      profiles: { type: Boolean, default: false }
    },
    linkedin: {
      feed: { type: Boolean, default: true },
      messaging: { type: Boolean, default: false }
    }
  },
  optimization: {
    bidStrategy: {
      type: String,
      enum: ['lowest_cost', 'cost_cap', 'bid_cap', 'target_cost'],
      default: 'lowest_cost'
    },
    bidAmount: {
      type: Number,
      min: [0.01, 'Bid amount must be at least 0.01']
    },
    optimizationGoal: {
      type: String,
      enum: ['impressions', 'clicks', 'reach', 'conversions'],
      default: 'impressions'
    }
  },
  performance: {
    impressions: { type: Number, default: 0 },
    clicks: { type: Number, default: 0 },
    reach: { type: Number, default: 0 },
    frequency: { type: Number, default: 0 },
    ctr: { type: Number, default: 0 },
    cpc: { type: Number, default: 0 },
    cpm: { type: Number, default: 0 },
    conversions: { type: Number, default: 0 },
    conversionRate: { type: Number, default: 0 },
    costPerConversion: { type: Number, default: 0 },
    spend: { type: Number, default: 0 },
    roi: { type: Number, default: 0 }
  },
  platformData: {
    campaignId: String,
    adAccountId: String,
    status: String,
    lastSync: Date,
    error: String
  },
  tags: [String],
  notes: String,
  isActive: {
    type: Boolean,
    default: true
  }
}, {
  timestamps: true,
  toJSON: { virtuals: true },
  toObject: { virtuals: true }
});

// Virtual for campaign duration
campaignSchema.virtual('duration').get(function() {
  if (!this.schedule.startDate || !this.schedule.endDate) return 0;
  return Math.ceil((this.schedule.endDate - this.schedule.startDate) / (1000 * 60 * 60 * 24));
});

// Virtual for budget remaining
campaignSchema.virtual('budgetRemaining').get(function() {
  return this.budget.amount - this.budget.spent;
});

// Virtual for budget spent percentage
campaignSchema.virtual('budgetSpentPercentage').get(function() {
  return (this.budget.spent / this.budget.amount) * 100;
});

// Virtual for campaign age
campaignSchema.virtual('age').get(function() {
  if (!this.schedule.startDate) return 0;
  const now = new Date();
  const start = new Date(this.schedule.startDate);
  return Math.ceil((now - start) / (1000 * 60 * 60 * 24));
});

// Indexes for better query performance
campaignSchema.index({ user: 1, status: 1 });
campaignSchema.index({ platform: 1, status: 1 });
campaignSchema.index({ 'schedule.startDate': 1, 'schedule.endDate': 1 });
campaignSchema.index({ createdAt: -1 });

// Pre-save middleware to validate dates
campaignSchema.pre('save', function(next) {
  if (this.schedule.startDate && this.schedule.endDate) {
    if (this.schedule.startDate >= this.schedule.endDate) {
      return next(new Error('End date must be after start date'));
    }
  }
  next();
});

// Method to update performance metrics
campaignSchema.methods.updatePerformance = function(metrics) {
  Object.assign(this.performance, metrics);
  
  // Calculate derived metrics
  if (this.performance.impressions > 0) {
    this.performance.ctr = (this.performance.clicks / this.performance.impressions) * 100;
    this.performance.cpm = (this.performance.spend / this.performance.impressions) * 1000;
  }
  
  if (this.performance.clicks > 0) {
    this.performance.cpc = this.performance.spend / this.performance.clicks;
  }
  
  if (this.performance.conversions > 0) {
    this.performance.conversionRate = (this.performance.conversions / this.performance.clicks) * 100;
    this.performance.costPerConversion = this.performance.spend / this.performance.conversions;
  }
  
  return this.save();
};

// Method to check if campaign is active
campaignSchema.methods.isActive = function() {
  const now = new Date();
  return this.status === 'active' && 
         this.schedule.startDate <= now && 
         this.schedule.endDate >= now;
};

// Method to pause campaign
campaignSchema.methods.pause = function() {
  this.status = 'paused';
  return this.save();
};

// Method to activate campaign
campaignSchema.methods.activate = function() {
  this.status = 'active';
  return this.save();
};

module.exports = mongoose.model('Campaign', campaignSchema);