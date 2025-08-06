const mongoose = require('mongoose');

const adSchema = new mongoose.Schema({
  user: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User',
    required: true
  },
  campaign: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'Campaign',
    required: true
  },
  name: {
    type: String,
    required: [true, 'Ad name is required'],
    trim: true,
    maxlength: [100, 'Ad name cannot exceed 100 characters']
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
  adType: {
    type: String,
    enum: [
      'image', 'video', 'carousel', 'story', 'reel', 'collection', 'catalog',
      'lead_form', 'messenger', 'app_install', 'dynamic', 'brand_awareness'
    ],
    required: [true, 'Ad type is required']
  },
  status: {
    type: String,
    enum: ['draft', 'pending', 'active', 'paused', 'rejected', 'completed', 'cancelled'],
    default: 'draft'
  },
  creative: {
    primaryText: {
      type: String,
      required: [true, 'Primary text is required'],
      maxlength: [2000, 'Primary text cannot exceed 2000 characters']
    },
    headline: {
      type: String,
      maxlength: [40, 'Headline cannot exceed 40 characters']
    },
    description: {
      type: String,
      maxlength: [125, 'Description cannot exceed 125 characters']
    },
    callToAction: {
      type: String,
      enum: [
        'shop_now', 'learn_more', 'sign_up', 'download', 'book_now', 'contact_us',
        'get_quote', 'apply_now', 'subscribe', 'donate_now', 'get_offer'
      ]
    },
    images: [{
      url: String,
      altText: String,
      width: Number,
      height: Number,
      size: Number, // in bytes
      format: String
    }],
    videos: [{
      url: String,
      thumbnail: String,
      duration: Number, // in seconds
      size: Number, // in bytes
      format: String
    }],
    carousel: [{
      title: String,
      description: String,
      image: String,
      link: String
    }]
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
    excludedAudiences: [String],
    placements: [String]
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
  bidding: {
    strategy: {
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
    roi: { type: Number, default: 0 },
    engagement: { type: Number, default: 0 },
    videoViews: { type: Number, default: 0 },
    videoViewRate: { type: Number, default: 0 },
    shares: { type: Number, default: 0 },
    comments: { type: Number, default: 0 },
    likes: { type: Number, default: 0 }
  },
  platformData: {
    adId: String,
    adSetId: String,
    status: String,
    lastSync: Date,
    error: String,
    approvalStatus: {
      type: String,
      enum: ['pending', 'approved', 'rejected'],
      default: 'pending'
    },
    rejectionReason: String
  },
  tracking: {
    pixelId: String,
    conversionEvents: [String],
    customParameters: [{
      key: String,
      value: String
    }],
    utmParameters: {
      source: String,
      medium: String,
      campaign: String,
      term: String,
      content: String
    }
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

// Virtual for ad duration
adSchema.virtual('duration').get(function() {
  if (!this.schedule.startDate || !this.schedule.endDate) return 0;
  return Math.ceil((this.schedule.endDate - this.schedule.startDate) / (1000 * 60 * 60 * 24));
});

// Virtual for budget remaining
adSchema.virtual('budgetRemaining').get(function() {
  return this.budget.amount - this.budget.spent;
});

// Virtual for budget spent percentage
adSchema.virtual('budgetSpentPercentage').get(function() {
  return (this.budget.spent / this.budget.amount) * 100;
});

// Virtual for ad age
adSchema.virtual('age').get(function() {
  if (!this.schedule.startDate) return 0;
  const now = new Date();
  const start = new Date(this.schedule.startDate);
  return Math.ceil((now - start) / (1000 * 60 * 60 * 24));
});

// Virtual for engagement rate
adSchema.virtual('engagementRate').get(function() {
  if (this.performance.impressions === 0) return 0;
  const totalEngagement = this.performance.likes + this.performance.comments + this.performance.shares;
  return (totalEngagement / this.performance.impressions) * 100;
});

// Indexes for better query performance
adSchema.index({ user: 1, status: 1 });
adSchema.index({ campaign: 1, status: 1 });
adSchema.index({ platform: 1, status: 1 });
adSchema.index({ 'schedule.startDate': 1, 'schedule.endDate': 1 });
adSchema.index({ createdAt: -1 });

// Pre-save middleware to validate dates
adSchema.pre('save', function(next) {
  if (this.schedule.startDate && this.schedule.endDate) {
    if (this.schedule.startDate >= this.schedule.endDate) {
      return next(new Error('End date must be after start date'));
    }
  }
  next();
});

// Method to update performance metrics
adSchema.methods.updatePerformance = function(metrics) {
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
  
  if (this.performance.videoViews > 0 && this.performance.impressions > 0) {
    this.performance.videoViewRate = (this.performance.videoViews / this.performance.impressions) * 100;
  }
  
  return this.save();
};

// Method to check if ad is active
adSchema.methods.isActive = function() {
  const now = new Date();
  return this.status === 'active' && 
         this.schedule.startDate <= now && 
         this.schedule.endDate >= now;
};

// Method to pause ad
adSchema.methods.pause = function() {
  this.status = 'paused';
  return this.save();
};

// Method to activate ad
adSchema.methods.activate = function() {
  this.status = 'active';
  return this.save();
};

// Method to duplicate ad
adSchema.methods.duplicate = function() {
  const newAd = new this.constructor({
    ...this.toObject(),
    _id: undefined,
    name: `${this.name} (Copy)`,
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
      roi: 0,
      engagement: 0,
      videoViews: 0,
      videoViewRate: 0,
      shares: 0,
      comments: 0,
      likes: 0
    },
    platformData: {
      status: 'draft',
      approvalStatus: 'pending'
    }
  });
  
  return newAd.save();
};

module.exports = mongoose.model('Ad', adSchema);