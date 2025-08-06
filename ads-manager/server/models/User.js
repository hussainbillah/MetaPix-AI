const mongoose = require('mongoose');
const bcrypt = require('bcryptjs');

const userSchema = new mongoose.Schema({
  email: {
    type: String,
    required: [true, 'Email is required'],
    unique: true,
    lowercase: true,
    trim: true,
    match: [/^\w+([.-]?\w+)*@\w+([.-]?\w+)*(\.\w{2,3})+$/, 'Please enter a valid email']
  },
  password: {
    type: String,
    required: [true, 'Password is required'],
    minlength: [6, 'Password must be at least 6 characters long']
  },
  firstName: {
    type: String,
    required: [true, 'First name is required'],
    trim: true,
    maxlength: [50, 'First name cannot exceed 50 characters']
  },
  lastName: {
    type: String,
    required: [true, 'Last name is required'],
    trim: true,
    maxlength: [50, 'Last name cannot exceed 50 characters']
  },
  company: {
    type: String,
    trim: true,
    maxlength: [100, 'Company name cannot exceed 100 characters']
  },
  role: {
    type: String,
    enum: ['user', 'admin', 'manager'],
    default: 'user'
  },
  isActive: {
    type: Boolean,
    default: true
  },
  emailVerified: {
    type: Boolean,
    default: false
  },
  lastLogin: {
    type: Date
  },
  preferences: {
    theme: {
      type: String,
      enum: ['light', 'dark', 'auto'],
      default: 'light'
    },
    timezone: {
      type: String,
      default: 'UTC'
    },
    currency: {
      type: String,
      default: 'USD'
    },
    notifications: {
      email: { type: Boolean, default: true },
      push: { type: Boolean, default: true },
      sms: { type: Boolean, default: false }
    }
  },
  subscription: {
    plan: {
      type: String,
      enum: ['free', 'basic', 'pro', 'enterprise'],
      default: 'free'
    },
    startDate: Date,
    endDate: Date,
    status: {
      type: String,
      enum: ['active', 'inactive', 'cancelled', 'expired'],
      default: 'active'
    }
  },
  apiKeys: [{
    name: String,
    key: String,
    permissions: [String],
    isActive: Boolean,
    lastUsed: Date,
    createdAt: { type: Date, default: Date.now }
  }],
  limits: {
    campaigns: { type: Number, default: 5 },
    ads: { type: Number, default: 50 },
    apiCalls: { type: Number, default: 1000 },
    storage: { type: Number, default: 100 } // MB
  },
  usage: {
    campaigns: { type: Number, default: 0 },
    ads: { type: Number, default: 0 },
    apiCalls: { type: Number, default: 0 },
    storage: { type: Number, default: 0 } // MB
  }
}, {
  timestamps: true,
  toJSON: { virtuals: true },
  toObject: { virtuals: true }
});

// Virtual for full name
userSchema.virtual('fullName').get(function() {
  return `${this.firstName} ${this.lastName}`;
});

// Virtual for usage percentage
userSchema.virtual('usagePercentage').get(function() {
  return {
    campaigns: (this.usage.campaigns / this.limits.campaigns) * 100,
    ads: (this.usage.ads / this.limits.ads) * 100,
    apiCalls: (this.usage.apiCalls / this.limits.apiCalls) * 100,
    storage: (this.usage.storage / this.limits.storage) * 100
  };
});

// Index for better query performance
userSchema.index({ email: 1 });
userSchema.index({ 'subscription.status': 1 });
userSchema.index({ createdAt: -1 });

// Pre-save middleware to hash password
userSchema.pre('save', async function(next) {
  if (!this.isModified('password')) return next();
  
  try {
    const salt = await bcrypt.genSalt(12);
    this.password = await bcrypt.hash(this.password, salt);
    next();
  } catch (error) {
    next(error);
  }
});

// Method to compare password
userSchema.methods.comparePassword = async function(candidatePassword) {
  return bcrypt.compare(candidatePassword, this.password);
};

// Method to generate API key
userSchema.methods.generateApiKey = function(name, permissions = []) {
  const key = require('crypto').randomBytes(32).toString('hex');
  const apiKey = {
    name,
    key,
    permissions,
    isActive: true,
    createdAt: new Date()
  };
  
  this.apiKeys.push(apiKey);
  return apiKey;
};

// Method to validate API key
userSchema.methods.validateApiKey = function(key) {
  const apiKey = this.apiKeys.find(k => k.key === key && k.isActive);
  if (apiKey) {
    apiKey.lastUsed = new Date();
    return apiKey;
  }
  return null;
};

// Method to check usage limits
userSchema.methods.checkUsageLimit = function(type) {
  return this.usage[type] < this.limits[type];
};

// Method to increment usage
userSchema.methods.incrementUsage = function(type, amount = 1) {
  this.usage[type] += amount;
  return this.save();
};

module.exports = mongoose.model('User', userSchema);